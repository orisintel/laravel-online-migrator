<?php

namespace OrisIntel\OnlineMigrator;

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migrator;
use Symfony\Component\Console\Output\ConsoleOutput;

class OnlineMigrator extends Migrator
{
    protected $output; // CONSIDER: Getting from provider

    private function getOutput()
    {
        if (! isset($this->output)) {
            $this->output = new ConsoleOutput();
        }

        return $this->output;
    }

    /**
     * Get all of the queries that would be run for a migration.
     *
     * @param object $migration
     * @param string $method
     *
     * @return array
     */
    protected function getQueries($migration, $method)
    {
        // BEGIN: Copied from parent.
        $db = $this->resolveConnection(
            $connection = $migration->getConnection()
        );
        // END: Copied from parent.

        // This should allow "--pretend" to work when changing any columns on
        // tables with enums.
        $this->registerEnumMapping($db);

        if (! self::isOnlineAppropriate($migration, $method, $db->getDatabaseName())) {
            return parent::getQueries($migration, $method);
        }

        if ('mysql' !== $db->getDriverName()) {
            throw new \InvalidArgumentException(
                'Database driver unsupported: ' . var_export($db->getDriverName(), 1));
        }

        // BEGIN: Copied from parent.
        $queries = $db->pretend(function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $migration->{$method}();
            }
        });
        // END: Copied from parent.

        $strategy = self::getStrategy($migration);
        foreach ($queries as &$query) {
            $query['query'] = $strategy::getQueryOrCommand($query, $db);
        }

        return $queries;
    }

    /**
     * Run a migration inside a transaction if the database supports it.
     *
     * @param object $migration
     * @param string $method
     *
     * @return void
     */
    protected function runMigration($migration, $method)
    {
        // BEGIN: Copied from parent.
        $connection = $this->resolveConnection(
            $migration->getConnection()
        );
        // END: Copied from parent.

        // Map enum even when not using Online Migrator to workaround Doctrine
        // blocking changes to any columns with tables containing enums.
        $this->registerEnumMapping($connection);

        if (! self::isOnlineAppropriate($migration, $method, $connection->getDatabaseName())) {
            parent::runMigration($migration, $method);

            return;
        }

        // HACK: Output immediately instead of waiting for runner to flush notes since
        // it can be slow and output is useful while in progress.
        foreach ($this->notes as $note) {
            $this->getOutput()->writeln($note);
        }
        $this->notes = [];

        // Instead of running the migration's callback unchanged, we need to
        // use the possibly reformatted query as a command.
        $queries = $this->getQueries($migration, $method);

        // CONSIDER: Trying to detect, and error out, when PHP code would have
        // non-DB changes since actual migration's PHP code won't be executed
        // directly (for ex. queue, cache, signal changes).

        // CONSIDER: Trying to run non-alters inside DB transaction.
        // CONSIDER: Emitting warning if $this->getSchemaGrammar($connection)->supportsSchemaTransactions().

        $strategy = self::getStrategy($migration);
        foreach ($queries as &$query) {
            $strategy::runQueryOrCommand($query, $connection);
        }
    }

    private function getStrategy($migration) : string
    {
        return str_start(
            studly_case(
                $migration->onlineStrategy
                ?? config('online-migrator.strategy', 'pt-online-schema-change')
            ),
            '\\OrisIntel\\OnlineMigrator\\Strategy\\'
        );
    }

    private function registerEnumMapping(Connection $db) : void
    {
        // CONSIDER: Moving to separate package since this isn't directly
        // related to online migrations.
        $enum_mapping = config('online-migrator.doctrine-enum-mapping');
        if ($enum_mapping) {
            $db->getDoctrineSchemaManager()
                ->getDatabasePlatform()
                ->registerDoctrineTypeMapping('enum', $enum_mapping);
        }
    }

    /**
     * @param Migration   $migration
     * @param string      $method
     * @param null|string $db_name
     *
     * @return bool
     */
    private static function isOnlineAppropriate(Migration &$migration, string &$method, ?string $db_name = '') : bool
    {
        // Traits on migrations themselves may rule out using this migrator.
        $is_online_compatible = (empty($migration->onlineIncompatibleMethods)
            || ! in_array($method, $migration->onlineIncompatibleMethods));

        $use_flag_specified = (0 < strlen(config('online-migrator.enabled')));

        // HACK: Work around slow and sometimes absent PTOSC by using original
        // queries when migrating "test" database(s).
        // CONSIDER: Instead leveraging configurable blacklist or per-DB option.
        $is_test_database = (false !== stripos($db_name, 'test'));

        $is_appropriate =
            ($use_flag_specified ? config('online-migrator.enabled') : (false === $is_test_database))
            && $is_online_compatible;

        return $is_appropriate;
    }
}
