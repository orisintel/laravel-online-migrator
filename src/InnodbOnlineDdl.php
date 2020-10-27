<?php

namespace OrisIntel\OnlineMigrator;

trait InnodbOnlineDdl
{
    /** @var string containing the desired strategy for the migration */
    public $onlineStrategy = 'innodb-online-ddl';
}
