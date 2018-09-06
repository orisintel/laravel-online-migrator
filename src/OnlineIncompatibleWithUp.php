<?php

namespace OrisIntel\OnlineMigrator\Traits;

// CONSIDER: Renaming "OnlineCannotMigrateUp"
trait OnlineIncompatibleWithUp
{
    /** @var array containing migrate methods incompatible w/OnlineMigrator */
    public $onlineIncompatibleMethods = ['up'];
}
