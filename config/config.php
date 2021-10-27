<?php

/*
 * You can place your custom package configuration in here.
 */

return [
    'enabled' => env('LARALOG_ENABLED', false),

    'sendlater' => true,

    // supported:
    'enrichers' => [
        \PKeidel\Laralog\Middleware\Logging::KEY_SQL => [
            PKeidel\Laralog\Enrichers\SqlCleanQueryEnricher::class,
//            PKeidel\Laralog\Enrichers\SqlFilledQueryEnricher::class,
        ],
        \PKeidel\Laralog\Middleware\Logging::KEY_REQUEST => [
            PKeidel\Laralog\Enrichers\RequestOpcacheInfoEnricher::class => [
                'directives' => [
                    'opcache.enable',
                    'opcache.memory_consumption',
                    'opcache.max_accelerated_files',
                    'opcache.revalidate_freq',
                ]
            ],
            PKeidel\Laralog\Enrichers\RequestApcuInfoEnricher::class,
        ],
        \PKeidel\Laralog\Middleware\Logging::KEY_RESPONSE => [],
        \PKeidel\Laralog\Middleware\Logging::KEY_STAT => [],
        \PKeidel\Laralog\Middleware\Logging::KEY_ERROR => [],
        \PKeidel\Laralog\Middleware\Logging::KEY_CACHEEVENT => [],
        \PKeidel\Laralog\Middleware\Logging::KEY_EVENT => [],
        \PKeidel\Laralog\Middleware\Logging::KEY_LOG => [],
    ],

//    'output' => [\PKeidel\Laralog\Outputs\ElasticsearchOutput::class],
    'output' => [
        \PKeidel\Laralog\Outputs\ElasticsearchOutput::class => [
//            'only' => [
//                \PKeidel\Laralog\Middleware\Logging::KEY_REQUEST,
//                \PKeidel\Laralog\Middleware\Logging::KEY_SQL,
//            ],
        ]
    ],

    'elasticsearch' => [
        'url' => env('LARALOG_ES_HOST'),
        'index' => static fn() => 'laralog-' . date('Y-m'),
        'username' => env('LARALOG_ES_USERNAME'),
        'password' => env('LARALOG_ES_PASSWORD'),
        'verifyssl' => env('LARALOG_ES_VERIFYSSL'),
        'pipeline' => env('LARALOG_ES_PIPELINE'),
    ],

    'telegraf' => [
        'host' => env('LARALOG_TG_HOST'),
        'port' => env('LARALOG_TG_PORT'),
    ],
];
