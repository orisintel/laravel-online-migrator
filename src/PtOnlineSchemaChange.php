<?php

namespace OrisIntel\OnlineMigrator;


trait PtOnlineSchemaChange
{
    /** @var string containing the desired strategy for the migration */
    public $onlineStrategy = 'pt-online-schema-change';
}
