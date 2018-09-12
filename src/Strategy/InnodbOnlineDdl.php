<?php

namespace OrisIntel\OnlineMigrator\Strategy;

use Illuminate\Database\Connection;

class InnodbOnlineDdl implements StrategyInterface
{
    private const INPLACE_INCOMPATIBLE = [
        'ALTER\s+TABLE\s+`?[^`\s]+`?\s+CHANGE', // CONSIDER: Only when type changes.
        'CONVERT\s+TO\s+CHARACTER\s+SET',
        'FOREIGN\s+KEY',
        'DROP\s+PRIMARY\s+KEY(?!,\s*ADD\s+PRIMARY\s+KEY)',
    ];

    /**
     * Get query or command, converting "ALTER TABLE " statements to on-line commands/queries.
     *
     * @param array $query
     * @param array $db_config
     *
     * @return string
     */
    public static function getQueryOrCommand(array &$query, array $db_config)
    {
        $query_or_command_str = $query['query'];

        // CONSIDER: Checking whether InnoDB table (and using diff. strategy?).
        $re = '/^\s*ALTER\s+TABLE\s+`?([^\s`]+)`?\s*/imu';
        if (preg_match($re, $query_or_command_str)) {
            // CONSIDER: Making algorithm and lock configurable generally and
            // per migration.
            // CONSIDER: Falling back to 'COPY' if 'INPLACE' is stopped.
            $algorithm = null;
            // CONSIDER: Supporting FKs by setting foreign_key_checks=OFF
            if (! preg_match('/\s*,\s*ALGORITHM\s*=\s*([^\s]+)/imu', $query_or_command_str, $m)) {
                // Some changes must be a 'COPY'.
                $algorithm = preg_match(
                    '/\b(' . implode('|', static::INPLACE_INCOMPATIBLE) . ')\b/iu',
                    $query_or_command_str
                ) ? 'COPY' : 'INPLACE';
                $query_or_command_str .= ', ALGORITHM=' . $algorithm;
            } else {
                $algorithm = strtoupper(trim($m[1]));
            }

            if (! preg_match('/\s*,\s*LOCK\s*=/iu', $query_or_command_str)) {
                $lock = 'COPY' === $algorithm ? 'SHARED' : 'NONE';
                $query_or_command_str .= ', LOCK=' . $lock;
            }
        }

        return $query_or_command_str;
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
        // Always run unchanged query since this strategy does not need to
        // execute commands of other tools.
        // CONSIDER: Moving this simple implementation to skeletal base class.
        $connection->affectingStatement($query['query'], $query['bindings']);
    }
}
