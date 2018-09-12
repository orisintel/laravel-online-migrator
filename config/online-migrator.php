<?php

return [
    'enabled' => env('USE_ONLINE_MIGRATOR'),

    'strategy' => env('ONLINE_MIGRATOR_STRATEGY', 'pt-online-schema-change'),

    'ptosc-options' => env('PTOSC_OPTIONS'),
];
