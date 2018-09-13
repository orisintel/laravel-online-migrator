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

    'enabled' => env('USE_ONLINE_MIGRATOR'),

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
];
