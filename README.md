# pkeidel/laralog

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pkeidel/laralog.svg?style=flat-square)](https://packagist.org/packages/pkeidel/laralog)
[![Total Downloads](https://img.shields.io/packagist/dt/pkeidel/laralog.svg?style=flat-square)](https://packagist.org/packages/pkeidel/laralog)

This package is inspired by [laravel-debugbar](https://github.com/barryvdh/laravel-debugbar) and [clockwork](https://github.com/itsgoingd/clockwork)
but logs the data to an elasticsearch server. It registers a middleware and the HTTP request to elasticsearch is send within `terminate()` by default to not slow down your page.

It logs: response data (like response time and http status code), database queries, most fired events, custom data.  

## Installation

You can install the package via composer:

```bash
composer require pkeidel/laralog
```

## Configuration
Add these values to your .env file:
```bash
LARALOG_ENABLED=true
LARALOG_ES_HOST=https://es01.example.com
LARALOG_ES_INDEX=myindex
LARALOG_ES_USERNAME=abcdefghi
LARALOG_ES_PASSWORD=!top5scr3t!
LARALOG_ES_VERIFYSSL=true
LARALOG_ES_PIPELINE=ipgeo
```

Or get the config/laralog.php file and modify it there.
For example to  
```bash
artisan vendor:publish --tag=config --provider="PKeidel\Laralog\LaralogServiceProvider"
```


## Log exceptions

Simply add this to `app/Exceptions/Handler::report(Exception $exception)`:

``` php
$pklaralog = resolve('pklaralog');

$pklaralog->get('errors')->push([
    'type'      => 'error',
    'time'      => round(microtime(true), 3),
    'exception' => get_class($exception),
    'message'   => $exception->getMessage(),
    'file'      => $exception->getFile(),
    'line'      => $exception->getLine(),
    'route'     => optional(request()->route())->uri() ?? 'unknown',
]);
```

## Outputs

### Elasticsearch
Create index template:

    artisan vendor:publish --tag=es-template --provider="PKeidel\Laralog\LaralogServiceProvider"
    artisan laralog:es:install

## Example Kibana visualisations
### Requests per route
![01_requests_per_route](img/01_requests_per_route.png)

## License

The MIT License (MIT)
