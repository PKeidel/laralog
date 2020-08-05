<?php

/*
 * You can place your custom package configuration in here.
 */

return [
    'enabled' => env('LARALOG_ENABLED', false),

    'sendlater' => true,

    // supported:
    'enrichers' => [
        'sql' => [
            PKeidel\Laralog\Enrichers\SqlCleanQueryEnricher::class,
            PKeidel\Laralog\Enrichers\SqlFilledQueryEnricher::class,
        ],
        'request' => [],
        'stats' => [],
        'errors' => [],
        'cacheevents' => [],
    ],

    'output' => [
        'type' => 'elasticsearch',
    ],

    'elasticsearch' => [
        'url' => env('LARALOG_ES_HOST'),
        'index' => env('LARALOG_ES_INDEX'),
        'username' => env('LARALOG_ES_USERNAME'),
        'password' => env('LARALOG_ES_PASSWORD'),
    ]
];
