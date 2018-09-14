<?php
/**
 * Created by PhpStorm.
 * User: progers
 * Date: 9/12/18
 * Time: 3:25 PM
 */

namespace OrisIntel\OnlineMigrator\Strategy;

use Illuminate\Database\Connection;

class PtOnlineSchemaChange implements StrategyInterface
{
    /** Mimicking non-strict defaults as closely as practical. */
    private const AUTO_DEFAULTS = [
        'boolean' => 'FALSE',
        'char' => "''",
        'date' => "'0001-01-01'",
        'datetime' => "'0001-01-01 00:00:00'",
        'decimal' => '0.0',
        'double' => '0.0',
        'int' => '0',
        'numeric' => '0.0',
        // Text cannot have a default unless non-strict mode.
        'time' => "'00:00:00'",
        'timestamp' => "'1970-01-01 00:00:01'",
        'varchar' => "''",
    ];

    /**
     * Get query or command, converting "ALTER TABLE " statements to on-line commands/queries.
     *
     * @param array $query
     * @param array $db_config
     *
     * @return string
     */
    public static function getQueryOrCommand(array &$query, Connection $connection)
    {
        $query_or_command_str = $query['query'];
        // CONSIDER: Executing --dry-run (only during pretend?) first to validate all will work.

        $table_name = null;
        $changes = null;

        $alter_re = '/\A\s*ALTER\s+TABLE\s+`?([^\s`]+)`?\s*/imu';
        $create_re = '/\A\s*CREATE\s+'
            . '((UNIQUE|FULLTEXT|SPATIAL)\s+)?'
            . 'INDEX\s+`?([^`\s]+)`?\s+ON\s+`?([^`\s]+)`?\s+?/imu';
        $drop_re = '/\A\s*DROP\s+'
            . 'INDEX\s+`?([^`\s]+)`?\s+ON\s+`?([^`\s]+)`?\s*?/imu';
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

            if (! empty($alter_parts)
                && config('online-migrator.ptosc-auto-defaults')
            ) {
                $changes = static::getChangesWithAutoDefaults($changes);
            }

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

    private static function getChangesWithAutoDefaults(string $raw_changes) : string
    {
        $changes = [];

        // Cannot do simple comma split because of types like "double(10, 5)".
        // CONSIDER: More robust parsing for split.
        foreach (preg_split('/,\s+(?!\d+\))/iu', $raw_changes) as $raw_change) {
            $change = $raw_change;
            // CONSIDER: Detecting column changes and auto-appending default
            // when changed from nullable to not-null and doesn't have default.
            if (preg_match('/\A\s*ADD\s+(COLUMN\s+)?`?[^`\s+]+`?\s+([^`\s+]+)(.*?\bNOT\s+NULL\b.*)/imu', $change, $add_parts)
                && ! preg_match('/\bDEFAULT\s+[^\s]+\b/imu', $add_parts[3])
            ) {
                $column_type = strtolower(
                    trim(
                        preg_replace(
                            '/(^BIG|^MEDIUM|^SMALL|^TINY|(INT)EGER|\(.*$)/iu',
                            '\2',
                            $add_parts[2]
                        )
                    )
                );
                if (isset(static::AUTO_DEFAULTS[$column_type])) {
                    $change .= ' DEFAULT ' . static::AUTO_DEFAULTS[$column_type];
                }
            }
            $changes[] = $change;
        }

        return implode(', ', $changes);
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
     * Execute query or on-line command.
     *
     * @param array      $query      like [ 'query' => string, 'bindings' => array ]
     * @param Connection $connection already established with database
     *
     * @throws \UnexpectedValueException
     *
     * @return void
     */
    public static function runQueryOrCommand(array &$query, Connection $connection)
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
