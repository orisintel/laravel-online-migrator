<?php

namespace OrisIntel\OnlineMigrator\Strategy;

use Illuminate\Database\Connection;

final class InnodbOnlineDdl implements StrategyInterface
{
    private const INPLACE_INCOMPATIBLE = [
        'ALTER\s+TABLE\s+`?[^`\s]+`?\s+(CHANGE|MODIFY)', // CONSIDER: Only when type changes.
        'CONVERT\s+TO\s+CHARACTER\s+SET',
        'DROP\s+PRIMARY\s+KEY(?!,\s*ADD\s+PRIMARY\s+KEY)',
        // Foreign keys depend upon state of foreign_key_checks.
    ];

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
        foreach ($queries as &$query) {
            $query['query'] = self::getQueryOrCommand($query, $connection);
        }

        return $queries;
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
        $query_or_command_str = rtrim($query['query'], '; ');

        $alter_re = '/\A\s*ALTER\s+TABLE\s+`?([^\s`]+)`?\s*(.*)/imu';
        $create_re = '/\A\s*CREATE\s+'
            . '((UNIQUE|FULLTEXT|SPATIAL)\s+)?'
            . 'INDEX\s+`?[^`\s]+`?\s+ON\s+`?([^\s`]+)`?/imu';
        $drop_re = '/\A\s*DROP\s+INDEX\s+`?[^`\s]+`?\s+ON\s+`?([^`\s]+)`?/imu';
        if (preg_match($alter_re, $query_or_command_str, $alter_parts)
            || preg_match($create_re, $query_or_command_str, $create_parts)
            || preg_match($drop_re, $query_or_command_str, $drop_parts)
        ) {
            // Changing engine already uses on-line DDL.
            if (0 === stripos($alter_parts[2] ?? '', 'ENGINE')) {
                return $query_or_command_str;
            }

            $table_name = $alter_parts[1] ?? $create_parts[3] ?? $drop_parts[1];
            $engine = $connection->table('information_schema.tables')
                ->where('table_name', $table_name)
                ->value('engine');
            // CONSIDER: Blacklisting known-to-be unsupported instead, or making
            // whitelist configurable, since others may have similar online DDL.
            if ('InnoDB' !== $engine) {
                // CONSIDER: Using different strategy, like PTOSC.
                return $query_or_command_str;
            }

            $separator = ! empty($alter_parts) ? ', ' : ' ';

            // CONSIDER: Making algorithm and lock configurable generally and
            // per migration.
            // CONSIDER: Falling back to 'COPY' if 'INPLACE' is stopped.
            $algorithm = null;
            if (! preg_match('/[\s,]\s*ALGORITHM\s*=\s*([^\s]+)/imu', $query_or_command_str, $algo_parts)) {
                $algorithm = static::isInplaceCompatible($query_or_command_str, $connection)
                    ? 'INPLACE' : 'COPY';

                $query_or_command_str .= $separator . 'ALGORITHM=' . $algorithm;
            } else {
                $algorithm = strtoupper(trim($algo_parts[1]));
            }

            if (! preg_match('/[\s,]\s*LOCK\s*=/iu', $query_or_command_str)) {
                $has_auto_increment = preg_match('/\bAUTO_INCREMENT\b/imu', $alter_parts[2] ?? '');
                // CONSIDER: Supporting non-alter statements like "CREATE (FULLTEXT) INDEX".
                $has_fulltext = preg_match('/\A\s*ADD\s+FULLTEXT\b/imu', $alter_parts[2] ?? '')
                    || 0 === stripos($create_parts[2] ?? '', 'FULLTEXT');
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
    public static function runQueryOrCommand(array &$query, Connection $connection) : void
    {
        // Always run unchanged query since this strategy does not need to
        // execute commands of other tools.
        // CONSIDER: Moving this simple implementation to skeletal base class.
        $connection->affectingStatement($query['query'], $query['bindings']);
    }
}
