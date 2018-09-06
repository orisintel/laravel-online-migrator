<?php

namespace OrisIntel\OnlineMigrator;

// CONSIDER: Renaming "OnlineCannotMigrateDown"
trait OnlineIncompatibleWithDown
{
    /** @var array containing migrate methods incompatible w/OnlineMigrator */
    public $onlineIncompatibleMethods = ['down'];
}
