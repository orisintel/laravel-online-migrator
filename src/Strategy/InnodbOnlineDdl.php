<?php

namespace OrisIntel\OnlineMigrator\Strategy;

use Illuminate\Database\Connection;

class InnodbOnlineDdl implements StrategyInterface
{
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
        $re = '/^\s*ALTER\s+TABLE\s+`?([^\s`]+)`?\s*/iu';
        if (preg_match($re, $query_or_command_str)
            // CONSIDER: Supporting FKs by setting foreign_key_checks=OFF or
            // falling back to 'COPY'.
            && ! preg_match('/\bFOREIGN\s+KEY\b/iu', $query_or_command_str)
        ) {
            // CONSIDER: Making algorithm and lock configurable generally and
            // per migration.
            // CONSIDER: Falling back to 'COPY' if 'INPLACE' is stopped.
            if (! preg_match('/\s*,\s*ALGORITHM\s*=/iu', $query_or_command_str)) {
                // Converting character set must be a 'COPY'.
                $algorithm = preg_match(
                    '/\bCONVERT\s+TO\s+CHARACTER\s+SET\b/iu',
                    $query_or_command_str
                ) ? 'COPY' : 'INPLACE';
                $query_or_command_str .= ', ALGORITHM=' . $algorithm;
            }
            if (! preg_match('/\s*,\s*LOCK\s*=/iu', $query_or_command_str)) {
                $query_or_command_str .= ', LOCK=NONE';
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
    public static function runQueryOrCommand(array &$query, Connection &$connection)
    {
        // Always run unchanged query since this strategy does not need to
        // execute commands of other tools.
        // CONSIDER: Moving this simple implementation to skeletal base class.
        $connection->affectingStatement($query['query'], $query['bindings']);
    }
}
