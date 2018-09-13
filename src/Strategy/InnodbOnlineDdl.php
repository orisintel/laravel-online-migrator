<?php

namespace OrisIntel\OnlineMigrator\Strategy;

use Illuminate\Database\Connection;

class InnodbOnlineDdl implements StrategyInterface
{
    private const INPLACE_INCOMPATIBLE = [
        'ALTER\s+TABLE\s+`?[^`\s]+`?\s+(CHANGE|MODIFY)', // CONSIDER: Only when type changes.
        'CONVERT\s+TO\s+CHARACTER\s+SET',
        'DROP\s+PRIMARY\s+KEY(?!,\s*ADD\s+PRIMARY\s+KEY)',
        // Foreign keys depend upon state of foreign_key_checks.
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
        $query_or_command_str = rtrim($query['query'], '; ');

        // CONSIDER: Checking whether InnoDB table (and using diff. strategy?).
        $re = '/\A\s*ALTER\s+TABLE\s+`?([^\s`]+)`?\s*(.*)/imu';
        if (preg_match($re, $query_or_command_str, $alter_parts)) {
            // CONSIDER: Making algorithm and lock configurable generally and
            // per migration.
            // CONSIDER: Falling back to 'COPY' if 'INPLACE' is stopped.
            $algorithm = null;
            if (! preg_match('/\s*,\s*ALGORITHM\s*=\s*([^\s]+)/imu', $query_or_command_str, $algo_parts)) {
                $algorithm = static::isInplaceCompatible($query_or_command_str, $connection)
                    ? 'INPLACE' : 'COPY';

                $query_or_command_str .= ', ALGORITHM=' . $algorithm;
            } else {
                $algorithm = strtoupper(trim($algo_parts[1]));
            }

            if (! preg_match('/\s*,\s*LOCK\s*=/iu', $query_or_command_str)) {
                $has_auto_increment = preg_match('/\bAUTO_INCREMENT\b/imu', $alter_parts[2]);
                $lock = 'COPY' === $algorithm || $has_auto_increment ? 'SHARED' : 'NONE';
                $query_or_command_str .= ', LOCK=' . $lock;
            }
        }

        return $query_or_command_str;
    }

    private static function isInplaceCompatible(string $query_str, Connection $connection) : bool
    {
        // Migration authors may disable FKs to allow in-place altering.
        // CONSIDER: Automatically setting foreign_key_checks=OFF, then back ON.
        if (preg_match('/\bFOREIGN\s+KEY\b/imu', $query_str)) {
            $foreign_key_checks = $connection->select('SELECT @@FOREIGN_KEY_CHECKS')[0]->{'@@FOREIGN_KEY_CHECKS'};
            if (1 === $foreign_key_checks) {
                return false;
            }
        }

        return preg_match(
            '/\b(' . implode('|', static::INPLACE_INCOMPATIBLE) . ')\b/imu',
            $query_str
        ) ? false : true;
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
