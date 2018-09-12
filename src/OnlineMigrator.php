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

        foreach ($queries as &$query) {
            $query['query'] = self::getQueryOrCommand($query, $db->getConfig());
        }

        return $queries;
    }

    /**
     * Get query or command, converting "ALTER TABLE " statements for pt-online-schema-change.
     *
     * @param array $query
     * @param array $db_config
     *
     * @return string
     */
    private static function getQueryOrCommand(array &$query, array $db_config)
    {
        $query_or_command_str = $query['query'];
        // CONSIDER: Instead mutating the SQL to use Mysql's online DDL, at least for InnoDB tables.
        // CONSIDER: Refactoring to strategy pattern based upon whether InnoDB table or not.
        // CONSIDER: Executing --dry-run (only during pretend?) first to validate all will work.
        // CONSIDER: Converting charset to "--charset...".

        $re = '/^\s*ALTER\s+TABLE\s+`?([^\s`]+)`?\s*/ui';
        if (preg_match($re, $query_or_command_str, $m)) {
            // Changing query so pretendToRun output will match command.
            // CONSIDER: Separate index and overriding pretendToRun instead.
            $changes = preg_replace($re, '', $query_or_command_str);

            // HACK: Workaround PTOSC quirk with escaping and defaults.
            $changes = str_replace(
                ["default '0'", "default '1'"],
                ['default 0', 'default 1'], $changes);

            // TODO: Fix dropping FKs by prefixing constraint name with '_' or
            // '__' if already starts with '_' (quirk in PTOSC).

            // Keeping defaults here so overriding one does not discard all, as
            // would happen if left to `config/online-migrator.php`.
            $ptosc_defaults = [
                '--alter-foreign-keys-method=auto',
                '--no-check-alter', // ASSUMES: Users accept risks w/RENAME.
                // ASSUMES: All are known to be unique.
                // CONSIDER: Extracting/re-creating automatic uniqueness checks
                // and running them here in PHP beforehand.
                '--no-check-unique-key-change',
            ];
            $ptosc_options_str = self::getOptionsForShell(
                config('online-migrator.ptosc-options'), $ptosc_defaults);

            if (false !== strpos($ptosc_options_str, '--dry-run')) {
                throw new \InvalidArgumentException(
                    'Cannot run PTOSC with --dry-run because it would incompletely change the database. Remove from PTOSC_OPTIONS.');
            }

            $query_or_command_str = 'pt-online-schema-change --alter '
                . escapeshellarg($changes)
                . ' D=' . escapeshellarg($db_config['database'] . ',t=' . $m[1])
                . ' --host ' . escapeshellarg($db_config['host'])
                . ' --port ' . escapeshellarg($db_config['port'])
                . ' --user ' . escapeshellarg($db_config['username'])
                // CONSIDER: Redacting password during pretend
                . ' --password ' . escapeshellarg($db_config['password'])
                . $ptosc_options_str
                . ' --execute'
                . ' 2>&1';
        }

        return $query_or_command_str;
    }

    /**
     * Get options from env. since artisan migrate has fixed arguments.
     *
     * ASSUMES: Shell command(s) use '--no-' to invert options when checking for defaults.
     *
     * @param string $option_csv
     * @param array  $defaults
     *
     * @return string
     */
    private static function getOptionsForShell(?string $option_csv, array $defaults = []) : string
    {
        $return = '';

        $options = [];
        foreach ($defaults as $raw_default) { // CONSIDER: Accepting value
            if (false === strpos($raw_default, '--', 0)) {
                throw new \InvalidArgumentException(
                    'Only double dashed (full) options supported '
                    . var_export($raw_default, 1));
            }
            $default_root = preg_replace('/(^--(no-?)?|=.*)/', '', $raw_default);
            $options[$default_root] = $raw_default;
        }

        if (! empty($option_csv)) {
            // CONSIDER: Formatting CLI options in config as native arrays
            // instead of CSV.
            // CONSIDER: Supporting commas embedded in option value like '--option="red,blue"'
            $raw_options = explode(',', $option_csv);
            foreach ($raw_options as $raw_option) {
                if (false === strpos($raw_option, '--', 0)) {
                    throw new \InvalidArgumentException(
                        'Only double dashed (full) options supported '
                        . var_export($raw_option, 1));
                }
                $option_root = preg_replace('/^--(no-?|=.*)?/', '', $raw_option);
                $options[$option_root] = $raw_option;
            }
        }

        foreach ($options as $raw_default) {
            $return .= ' ' . $raw_default; // TODO: Escape
        }

        return $return;
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

        foreach ($queries as &$query) {
            $this->runQueryOrCommand($query, $connection);
        }
    }

    /**
     * Execute query or pt-online-schema-change command.
     *
     * @param array      $query      like [ 'query' => string, 'bindings' => array ]
     * @param Connection $connection already established with database
     *
     * @throws \UnexpectedValueException
     *
     * @return void
     */
    private function runQueryOrCommand(array &$query, Connection &$connection)
    {
        // CONSIDER: Using unmodified migration code when small and not
        // currently locked table.
        // CONSIDER: Non-PTOSC specific prefix like "-- COMMAND:".
        if (0 === strpos($query['query'], 'pt-online-schema-change')) {
            $return_var = null;
            // CONSIDER: Converting migration verbosity switches into PTOSC
            // verbosity switches.
            $command = $query['query'];
            // Pass-through output instead of capturing since delay until end of
            // command may be infinite during prompt or too late to correct.
            passthru($command, $return_var);
            if (0 !== $return_var) {
                throw new \UnexpectedValueException('Exited with error code '
                    . var_export($return_var, 1) . ', command:' . PHP_EOL
                    . $query['query'],
                    intval($return_var));
            }
        } else {
            // Run unchanged query
            $connection->affectingStatement($query['query'], $query['bindings']);
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
