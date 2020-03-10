<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Option To Force Or Bypass OnlineMigrator
    |--------------------------------------------------------------------------
    |
    | When not specified it will use the on-line capabilities; except for DBs
    | with 'test' in their name.
    |
    | '0' bypasses any on-line tools and sends queries unchanged.
    | '1' (or any truthy value) forces use of the on-line tools.
    |
    */

    'enabled' => env('ONLINE_MIGRATOR'),

    /*
    |--------------------------------------------------------------------------
    | On-line Strategy
    |--------------------------------------------------------------------------
    |
    | Which tool or technique to use when trying to minimize disruptions during
    | migrations. Individual migrations may override this with traits.
    |
    | Currently there are only two values:
    | pt-online-schema-change
    | innodb-online-ddl
    |
    */

    'strategy' => env('ONLINE_MIGRATOR_STRATEGY', 'pt-online-schema-change'),

    /*
    |--------------------------------------------------------------------------
    | Percona Online Schema Change Options
    |--------------------------------------------------------------------------
    |
    | Accepts a comma-separated list of options for pt-online-schema-change.
    |
    | These options are always included unless overwritten with different
    | values:
    | --alter-foreign-keys-method=auto
    | --no-check-alter
    | --no-check-unique-key-change
    |
    */

    'ptosc-options' => env('PTOSC_OPTIONS'),

    /*
    |--------------------------------------------------------------------------
    | Percona Online Schema Change - Fold Table Case
    |--------------------------------------------------------------------------
    |
    | Whether to force table case to 'upper' or 'lower'. Mysql can silently
    | coerce case but PTOSC cannot.
    |
    */

    'ptosc-fold-table-case' => env('PTOSC_FOLD_TABLE_CASE'),

    /*
    |--------------------------------------------------------------------------
    | Register Doctrine Enum Type
    |--------------------------------------------------------------------------
    |
    | Works around altering non-enum columns blocked by quirk in Doctrine by
    | mapping enums to 'string'. Using 'string' does not allow changing enums
    | themselves with Eloquent like `->enum(...)->change()`. Can pass along any
    | truthy type name in case there are better alternatives.
    |
    */
    'doctrine-enum-mapping' => env('DOCTRINE_ENUM_MAPPING'),
];
