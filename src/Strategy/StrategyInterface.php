<?php

namespace OrisIntel\OnlineMigrator\Strategy;

use Illuminate\Database\Connection;

interface StrategyInterface
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
    public static function getQueriesAndCommands(array &$queries, Connection $connection, bool $combineIncompatible = false) : array;

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
    public static function runQueryOrCommand(array &$query, Connection $connection) : void;
}
