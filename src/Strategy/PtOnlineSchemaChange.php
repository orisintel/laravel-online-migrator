<?php
/**
 * Created by PhpStorm.
 * User: progers
 * Date: 9/12/18
 * Time: 3:25 PM
 */

namespace OrisIntel\OnlineMigrator\Strategy;

use Illuminate\Database\Connection;

final class PtOnlineSchemaChange implements StrategyInterface
{
    /**
     * Get queries and commands, converting "ALTER TABLE " statements to on-line commands/queries.
     *
     * @param array $queries
     * @param array $connection
     * @param bool  $combineIncompatible
     *
     * @return array of queries and--where supported--commands
     */
    public static function getQueriesAndCommands(array &$queries, Connection $connection, bool $combineIncompatible = false) : array
    {
        /*** @var array like ['table_name' => string, 'changes' => array]. */
        $combining = [];
        $queries_original_count = count($queries);
        $queries_commands = [];
        for ($i = 0; $i < $queries_original_count; $i += 1) {
            if (
                ! $combineIncompatible
                && $combinable = self::getCombinableTableAndChanges($queries[$i])
            ) {
                // First adjacent combinable.
                if (empty($combining)) {
                    $combining = $combinable;
                    continue;
                }

                // Different table, so store previous combinables.
                if ($combining['table_name'] != $combinable['table_name']) {
                    $queries_commands[] = self::getCombinedWithBindings($combining, $connection);
                    $combining = $combinable;
                    continue;
                }

                // Same table, so combine changes into comma-separated string.
                $combining['changes'] =
                    (! empty($combining['changes']) ? $combining['changes'] . ', ' : '')
                    . $combinable['changes'];
                continue;
            }

            // Not combinable, so store any previous combinables.
            if (! empty($combining)) {
                $queries_commands[] = self::getCombinedWithBindings($combining, $connection);
                $combining = [];
            }

            $queries[$i]['query'] = self::getQueryOrCommand($queries[$i], $connection);
            $queries_commands[] = $queries[$i];
        }

        // Store residual combinables so they aren't lost.
        if (! empty($combining)) {
            $queries_commands[] = self::getCombinedWithBindings($combining, $connection);
        }

        return $queries_commands;
    }

    /**
     * @param array      $combining like ['table_name' => string, 'changes' => string]
     * @param Connection $connection
     *
     * @return array like ['query' => string, 'binding' => array, 'time' => float].
     */
    private static function getCombinedWithBindings(array $combining, Connection $connection) : array
    {
        // TODO: Restore original table-name escapes.
        $query_bindings_time = [
            'query' => 'ALTER TABLE ' . $combining['table_name'] . ' ' . $combining['changes'],
            'bindings' => [],
            'time' => 0.0,
        ];
        $query_bindings_time['query'] = self::getQueryOrCommand($query_bindings_time, $connection);

        return $query_bindings_time;
    }

    /**
     * @return array like 'table_name' => string, 'changes' => string].
     */
    public static function getCombinableTableAndChanges(array $query) : array
    {
        // CONSIDER: Combining if all named or all unnamed.
        if (! empty($query['bindings'])) {
            return [];
        }

        $parts = self::getTableAndChanges($query['query']);

        // TODO: Only those known to be combinable.
        if (preg_match('/^\s*(ADD|DROP)?\b/imu', $parts['changes'])) {
            return $parts;
        }

        return [];
    }

    private static function getTableAndChanges(string $query_or_command_str) : array
    {
        $table_name = null;
        $changes = null;

        $alter_re = '/\A\s*ALTER\s+TABLE\s+[`"]?([^\s`"]+)[`"]?\s*/imu';
        $create_re = '/\A\s*CREATE\s+'
            . '((UNIQUE|FULLTEXT|SPATIAL)\s+)?'
            . 'INDEX\s+[`"]?([^`"\s]+)[`"]?\s+ON\s+[`"]?([^`"\s]+)[`"]?\s+?/imu';
        $drop_re = '/\A\s*DROP\s+'
            . 'INDEX\s+[`"]?([^`"\s]+)[`"]?\s+ON\s+[`"]?([^`"\s]+)[`"]?\s*?/imu';
        if (preg_match($alter_re, $query_or_command_str, $alter_parts)) {
            $table_name = $alter_parts[1];
            // Changing query so pretendToRun output will match command.
            // CONSIDER: Separate index and overriding pretendToRun instead.
            $changes = preg_replace($alter_re, '', $query_or_command_str);
        } elseif (preg_match($create_re, $query_or_command_str, $create_parts)) {
            $index_name = $create_parts[3];
            $table_name = $create_parts[4];
            $changes = "ADD $create_parts[2] INDEX $index_name "
                . preg_replace($create_re, '', $query_or_command_str);
        } elseif (preg_match($drop_re, $query_or_command_str, $drop_parts)) {
            $index_name = $drop_parts[1];
            $table_name = $drop_parts[2];
            $changes = "DROP INDEX $index_name "
                . preg_replace($drop_re, '', $query_or_command_str);
        }

        if ($table_name && $changes) {
            // HACK: Workaround PTOSC quirk with escaping and defaults.
            $changes = str_replace(
                ["default '0'", "default '1'"],
                ['default 0', 'default 1'], $changes);

            // Dropping FKs with PTOSC requires prefixing constraint name with
            // '_'; adding another if it already starts with '_'.
            $changes = preg_replace('/(\bDROP\s+FOREIGN\s+KEY\s+[`"]?)([^`"\s]+)/imu', '\01_\02', $changes);
        }

        return compact('table_name', 'changes');
    }

    /**
     * Get query or command, converting "ALTER TABLE " statements to on-line commands/queries.
     *
     * @param array $query
     * @param array $connection
     *
     * @return string
     */
    public static function getQueryOrCommand(array &$query, Connection $connection) : string
    {
        $query_or_command_str = $query['query'];
        // CONSIDER: Executing --dry-run (only during pretend?) first to validate all will work.

        $parts = self::getTableAndChanges($query_or_command_str);
        $table_name = $parts['table_name'];
        $changes = $parts['changes'];

        if ($table_name && $parts) {
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

            $db_config = $connection->getConfig();
            $query_or_command_str = 'pt-online-schema-change --alter '
                . escapeshellarg($changes)
                . ' D=' . escapeshellarg($db_config['database'] . ',t=' . $table_name)
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
    public static function getOptionsForShell(?string $option_csv, array $defaults = []) : string
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
            $raw_options = preg_split('/[, ]+(?=--)/', $option_csv);
            foreach ($raw_options as $raw_option) {
                if (false === strpos($raw_option, '--', 0)) {
                    throw new \InvalidArgumentException(
                        'Only double dashed (full) options supported '
                        . var_export($raw_option, 1));
                }
                $option_root = preg_replace('/(^--(no-?)?|[= ].*)?/', '', $raw_option);
                $options[$option_root] = $raw_option;
            }
        }

        foreach ($options as $raw_default) {
            $return .= ' ' . $raw_default; // TODO: Escape
        }

        return $return;
    }

    /**
     * Execute query or on-line command.
     *
     * @param array      $query      like [ 'query' => string, 'bindings' => array ]
     * @param Connection $connection already established with database
     *
     * @throws \UnexpectedValueException
     *
     * @return void
     */
    public static function runQueryOrCommand(array &$query, Connection $connection) : void
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
}
