<?php

namespace PKeidel\Laralog\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PKeidel\Laralog\Enrichers\ILaralogEnricher;
use PKeidel\Laralog\Outputs\OutputManager;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Symfony\Component\HttpFoundation\HeaderBag;

class Logging {

    private string $requestId;
    private OutputManager $output;
    private bool $sendLater;

    public const KEY_SQL = 'sql';
    public const KEY_CACHEEVENT = 'cacheevent';
    public const KEY_EVENT = 'event';
    public const KEY_ERROR = 'error';
    public const KEY_LOG = 'log';
    public const KEY_REQUEST = 'request';
    public const KEY_RESPONSE = 'response';
    public const KEY_STAT = 'stat';

    private static array $datacollection = [];

    public function __construct() {
        $this->sendLater = config('laralog.sendlater', true);
        $this->requestId = bin2hex(random_bytes(5));
        $this->output = new OutputManager();
    }

    public static function currentTS() {
        return round(microtime(true), 3) * 1000;
    }

    private function isEnabled() {
        return config('laralog.enabled');
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next) {
        if(!$this->isEnabled()) {
            return $next($request);
        }

        $this->createEmptyCollectionArray();
        $this->registerListeners();

        $response = $next($request);
        $response->headers->set('X-REQID', $this->requestId);
        static::$datacollection[static::KEY_REQUEST]['duration'] = static::currentTS() - static::$datacollection['start'];

        if(!$this->sendLater) {
            $this->log($request, $response);
        }

        return $response;
    }

    public function createEmptyCollectionArray(): void {
        static::$datacollection = [
            'start' => static::currentTS(),
        ];
    }

    public function terminate($request, $response): void {
        if(!$this->sendLater || !$this->isEnabled()) {
            return;
        }

        $this->log($request, $response);
    }

    private function log(Request $request, Response $response): void {
        $this->setupOutputs();

        // Save request to singleton
        $this->saveRequest($request);
        $this->saveResponse($response);
        $this->saveStats();

        foreach([static::KEY_SQL, static::KEY_CACHEEVENT, static::KEY_EVENT, static::KEY_ERROR, static::KEY_LOG] as $key) {
            // '$key' to stand-alone log objects
            $data = $this->cleanUpCollectionAndGet($key);
            foreach($data as $arr) {
                $this->prepareData($key, $arr, $request);
            }
        }

        foreach([static::KEY_STAT, static::KEY_REQUEST, static::KEY_RESPONSE] as $key) {
            // '$key' to stand-alone log objects
            $arr = $this->cleanUpCollectionAndGet($key);
            $this->prepareData($key, $arr, $request);
        }

        $this->output->send();
    }

    private function prepareData(string $key, $arr, Request $request): void {
        $this->enrichData($key, $arr);
        $arr['time'] ??= static::currentTS();
        $arr['route'] ??= $request->route() !== NULL ? $request->route()->uri() : $request->path();
        $arr['method'] ??= $request->getMethod();
        $arr['reqId'] ??= $this->requestId;
        $this->output->prepareData($key, $arr);
    }

    private function enrichData(string $type, array &$data): void {
        $enricherClasses = config("laralog.enrichers.$type");

        if(is_array($enricherClasses) && count($enricherClasses) && is_array($enricherClasses)) {
            // run registered enrichers
            foreach($enricherClasses as $enricherClass => $enricherClassOrConfig) {
                try {
                    $enricherArgs = [];

                    // class could be the value
                    // class could be the key with constuctor args as values
                    if(is_array($enricherClassOrConfig)) {
                        $enricherArgs = $enricherClassOrConfig;
                    } else {
                        $enricherClass = $enricherClassOrConfig;
                    }

                    /** @var ILaralogEnricher $enricher */
                    $enricher = new $enricherClass($enricherArgs);
                    $enricher->enrichFrom($data);
                } catch (\Throwable $t) {
                    // ignore because it could cause an endless loop
                }
            }
        }

    }

    private function registerListeners(): void {
        Event::listen('*', function($eventName, $data) {

            $caller = $this->getCaller();

            $eventNameOrig = $eventName;

            $infos     = explode(': ', $eventName);
            $eventName = $infos[0];
            $key       = $infos[1] ?? '';

            $this->incCounter($eventName);

            switch($eventName) {

                // query speichern
                case QueryExecuted::class:
                    $this->saveQuery($data[0]);
                    break;

                // cache abfragen speichern
                case CacheHit::class:
                case CacheMissed::class:
                case KeyWritten::class:
                case KeyForgotten::class:
                    $this->incCounter('cache'); // sum of all Cache-Events
                    static::$datacollection[static::KEY_CACHEEVENT][] = [
                        'time' => static::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'key' => $data[0]->key
                    ];
                    break;

                // ignorieren
                case StatementPrepared::class:
                case 'OwenIt\Auditing\Events\Auditing':
                case 'eloquent.booting':
                case 'eloquent.retrieved':
                case 'eloquent.updating':
                case 'eloquent.updated':
                case 'eloquent.saving':
                case 'eloquent.saved':
                case 'eloquent.creating':
                case 'eloquent.created':
                case 'eloquent.deleting':
                case 'eloquent.deleted':
                case 'creating':
                case 'composing':
                    break;

                case Authenticated::class:
                case \App\Events\ApplicationCreatedEvent::class:
                    $esData = [];

                    if(isset($data[0]->user)) {
                        $esData['user'] = $data[0]->user->only(['id']);
                    }

                    if(isset($data[0]->event)) {
                        $esData['event'] = $data[0]->event->only(['id']);
                    }

                    if(isset($data[0]->application)) {
                        $esData['application'] = $data[0]->application->only(['id', 'eventtimes_id', 'type']);
                    }

                    static::$datacollection[static::KEY_EVENT][] = [
                        'time' => static::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'eventorig' => $eventNameOrig,
                        'data'  => $esData,
                    ];
                    break;

                // benötigt als info für sql abfragen
                case 'eloquent.booted':
                    // TODO ?????
//                    $this->datacollection->put('lastmodel', get_class($data[0]));
                    static::$datacollection['lastmodel'] = get_class($data[0]);
                    break;

                case MessageLogged::class:
                    static::$datacollection[static::KEY_LOG][] = [
                        'time' => static::currentTS(),
                        'caller' => $caller,
                        'data'  => $data[0],
                    ];
                    break;

                case RouteMatched::class:
                    static::$datacollection[static::KEY_EVENT][] = [
                        'time' => static::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'eventorig' => $eventNameOrig,
                        'key'  => $data[0]->request->getPathinfo(),
                    ];
                    break;

                default:
                    static::$datacollection[static::KEY_EVENT][] = [
                        'time' => static::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'eventorig' => $eventNameOrig,
                        'key' => $key
                    ];
            }
        });
    }

    private function incCounter($name, int $amount = 1): void {
        static::$datacollection[static::KEY_STAT] ??= [];
        static::$datacollection[static::KEY_STAT]['counter'] ??= [];
        static::$datacollection[static::KEY_STAT]['counter'][$name] ??= 0;
        static::$datacollection[static::KEY_STAT]['counter'][$name] += $amount;
    }

    private function saveQuery($data): void {
        static::$datacollection[static::KEY_SQL][] = [
            'time'         => static::currentTS(),
            'query'        => $data->sql,
            'bindingsorig' => array_map(static fn($v) => (string) $v, $data->bindings ?? []) ?? [],
            'queryType'    => strtoupper(Arr::first(explode(' ', $data->sql))),
            'duration'     => (float) $data->time,
            'caller'       => $this->getCaller(),
            'model'        => static::$datacollection['lastmodel'] ?? '-',
        ];
    }

    private function saveRequest(Request $request): void {
        static::$datacollection[static::KEY_REQUEST] = [
            'host'    => $request->getHttpHost(),
            'uri'     => $request->server('REQUEST_URI'),
            'method'  => $request->getMethod(),
            'headers' => Arr::only($this->headersToArray($request->headers), [
                'host', 'user-agent', 'accept', 'content-type'
            ]),
            'ip' => $request->header('X-Forwarded-For') ?? $request->getClientIp(),
        ];

        if(Auth::user() !== null) {
            static::$datacollection[static::KEY_REQUEST]['user'] = [
                'id'       => $request->user()->id,
                'username' => $request->user()->username,
            ];
        }
    }

    private function saveResponse(Response $response): void {
        static::$datacollection[static::KEY_RESPONSE] = [
            'time'   => static::currentTS(),
            'status' => $response->getStatusCode(),
            'bytes'  => mb_strlen($response->getContent()),
        ];
    }

    private function saveStats(): void {
        static::$datacollection[static::KEY_STAT]['memory'] = ['peak' => memory_get_peak_usage(true)];

        if(array_key_exists(static::KEY_SQL, static::$datacollection)) {
            static::$datacollection[static::KEY_STAT][static::KEY_SQL] = [
                'count' => count(static::$datacollection[static::KEY_SQL]),
                'totalTime' => array_reduce(static::$datacollection[static::KEY_SQL], static fn($sumMs, $sql) => $sql['duration'] + $sumMs, 0),
            ];
        }
    }

    private function cleanUpCollectionAndGet($key) {
        $data = static::$datacollection[$key] ?? [];
        unset(static::$datacollection[$key]);
        return $data;
    }

    /**
     * @param HeaderBag $headers
     * @return array
     */
    private function headersToArray(HeaderBag $headers): array {
        $ret = [];
        foreach($headers->getIterator() as $key => $value) {
            $ret[$key] = $value[0];
        }
        return $ret;
    }

    public function getCaller(): array {
        return Arr::except((array) $this->getBacktrace()->first(function($trace) {
            if(empty($trace->file))
                return false;
            if(strpos($trace->file, __FILE__) !== FALSE)
                return false;
            if(strpos($trace->file, '/vendor/pkeidel/') !== FALSE)
                return false;
            if(strpos($trace->file, '/vendor/laravel/') !== FALSE)
                return false;
            if(strpos($trace->file, '/vendor/illuminate/') !== FALSE)
                return false;
            if(strpos($trace->file, 'Middleware') !== FALSE)
                return false;
            return true;
        }), ['object']);
    }

    private function getBacktrace(): \Illuminate\Support\Collection {
        return collect(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS))->map(function($a) {
            return (object) $a;
        });
    }

    private function setupOutputs(): void {
        $outputType = config("laralog.output");

        // $outputType '\PKeidel\Laralog\Outputs\ElasticsearchOutput'
        if (is_string($outputType)) {
            $outputType = [$outputType];
        }

        // $outputType ['\PKeidel\Laralog\Outputs\ElasticsearchOutput', '..']
        // $outputType ['\PKeidel\Laralog\Outputs\ElasticsearchOutput' => [ .. config .. ]]
        foreach ($outputType as $outputClass => $outputConfig) {
            if (is_numeric($outputClass)) {
                // there is only the class and no config
                $outputClass = $outputConfig;
                $outputConfig = [];
            }

            if (!class_exists($outputClass)) {
                Log::alert("Laralog::Logging Output '$outputClass' is not known. Data can not be logged.");
                continue;
            }

            $this->output->add(new $outputClass($outputConfig));
        }
    }

    public static function reportThrowable(\Throwable $throwable, array $extraData = []): void {
        static::$datacollection ??= [];
        static::$datacollection[static::KEY_ERROR][] = [
            'time'      => static::currentTS(),
            'exception' => get_class($throwable),
            'message'   => $throwable->getMessage(),
            'file'      => $throwable->getFile(),
            'line'      => $throwable->getLine(),
            'route'     => optional(request()->route())->uri() ?? 'unknown',
        ] + $extraData;
    }
}
