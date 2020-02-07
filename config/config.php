<?php

/*
 * You can place your custom package configuration in here.
 */

use App\Eve42Helper;

return [
    'enabled' => env('LARALOG_ENABLED', false),

    // supported:
    'enrichers' => [
        'sql' => [
            PKeidel\laralog\src\Enrichers\SqlCleanQueryEnricher::class,
        ],
        'request' => [
            App\Laralog\Enrichers\RandomValue::class,
        ],
        'stats' => [],
        'errors' => [],
        'cacheevents' => [],
    ],

    'output' => [
        'host' => env('LARALOG_ES_HOST'),
        'type' => 'elasticsearch',
        'index' => fn() => "laravel-".Eve42Helper::getSubDomain().'-'.date('Y-m'),
        'username' => env('LARALOG_ES_USERNAME'),
        'password' => env('LARALOG_ES_PASSWORD'),
    ]
];
