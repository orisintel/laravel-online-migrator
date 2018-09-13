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
        $alter_re = '/\A\s*ALTER\s+TABLE\s+`?[^\s`]+`?\s*(.*)/imu';
        $create_re = '/\A\s*CREATE\s+'
            . '((ONLINE|OFFLINE)\s+)?'
            . '((UNIQUE|FULLTEXT|SPATIAL)\s+)?'
            . 'INDEX\s+/imu';
        if (preg_match($alter_re, $query_or_command_str, $alter_parts)
            || preg_match($create_re, $query_or_command_str, $create_parts)
        ) {
            $separator = ! empty($alter_parts) ? ', ' : ' ';

            // CONSIDER: Making algorithm and lock configurable generally and
            // per migration.
            // CONSIDER: Falling back to 'COPY' if 'INPLACE' is stopped.
            $algorithm = null;
            if (! preg_match('/\s*,\s*ALGORITHM\s*=\s*([^\s]+)/imu', $query_or_command_str, $algo_parts)) {
                $algorithm = static::isInplaceCompatible($query_or_command_str, $connection)
                    ? 'INPLACE' : 'COPY';

                $query_or_command_str .= $separator . 'ALGORITHM=' . $algorithm;
            } else {
                $algorithm = strtoupper(trim($algo_parts[1]));
            }

            if (! preg_match('/\s*,\s*LOCK\s*=/iu', $query_or_command_str)) {
                $has_auto_increment = preg_match('/\bAUTO_INCREMENT\b/imu', $alter_parts[1] ?? '');
                // CONSIDER: Supporting non-alter statements like "CREATE (FULLTEXT) INDEX".
                $has_fulltext = preg_match('/\A\s*ADD\s+FULLTEXT\b/imu', $alter_parts[1] ?? '')
                    || 0 === stripos($create_parts[4] ?? '', 'FULLTEXT');
                $lock = 'COPY' === $algorithm || $has_auto_increment || $has_fulltext
                    ? 'SHARED' : 'NONE';
                $query_or_command_str .= $separator . 'LOCK=' . $lock;
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
