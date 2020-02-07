<?php

namespace PKeidel\Laralog\Middleware;

use App\Eve42Helper;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PKeidel\laralog\src\Enrichers\ILaralogEnricher;
use PKeidel\laralog\src\Outputs\ElasticsearchOutput;
use PKeidel\laralog\src\Outputs\IOutput;

class Logging {

    private $esurl = null;
    private $esuser = null;
    private $espassword = null;
    private $uuid = null;
    private $fallbackfile = null;
    private IOutput $output;
    private bool $sendLater = true;

    /** @var \Illuminate\Support\Collection */
    private $datacollection = null;

    public function __construct() {
        $this->fallbackfile = storage_path('logs/elasticsearch_bulk.log.gz9');
    }

    public static function currentTS() {
        return round(microtime(true), 3);
    }

    private function isEnabled() {
        return config('laralog.enabled');
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next) {

        if(!$this->isEnabled()) {
            return $next($request);
        }

        $this->prepareDataCollecting($request);

        $response = $next($request);
        $response->headers->set('X-UUID', $this->uuid);
        $this->datacollection->put('duration', round((self::currentTS() - $this->datacollection->pull('start')) * 1000));

        if(!$this->sendLater)
            $this->log($request, $response);

        return $response;
    }

    public function prepareDataCollecting(Request $request) {
        $this->datacollection = $this->registerSingletons();
        $this->datacollection->put('start', self::currentTS());
        $this->registerListeners();

        $method = $request->getMethod();
        $uri    = $request->server('REQUEST_URI');
        $host   = $request->server('SERVER_NAME');
        $this->datacollection->get('request')->put('host', $host);
        $this->datacollection->get('request')->put('uri', $uri);
        $this->datacollection->get('request')->put('method', $method);
    }

    public function terminate($request, $response) {
        if($this->sendLater)
            $this->log($request, $response);
    }

    private function log($request, $response) {
        $this->datacollection->put('stats', [
            'memory' => memory_get_peak_usage(true),
        ]);

        $outputType = config("laralog.output.type");

        if($outputType === 'elasticsearch') {
            $this->output = new ElasticsearchOutput();
        } else {
            Log::alert("Laralog::Logging Output '$outputType' is not known. Data can not be logged.");
            return;
        }

        // Save request to singleton
        $this->saveRequestToSingleton($request);

        $this->datacollection->put('response', [
            'status' => $response->getStatusCode(),
        ]);

        // 'sql' zu eigenen Objekten machen
        $sqls = $this->cleanUpCollectionAndGet('sql');
        $sqlStats = ['totalTime' => 0];
        foreach($sqls as $arr) {
            $sqlStats['totalTime'] += $arr['duration'];
            $arr = $this->getEnrichedData('sql', $arr);
            $this->output->prepareData('sql', $arr);
        }

        // 'cacheevents' zu eigenen Objekten machen
        $cacheevents = $this->cleanUpCollectionAndGet('cacheevents');
        foreach($cacheevents as $arr) {
            $arr = $this->getEnrichedData('cacheevent', $arr);
            $this->output->prepareData('cacheevent', $arr);
        }

        // 'allevents' zu eigenen Objekten machen
        $allevents = $this->cleanUpCollectionAndGet('allevents');
        foreach($allevents as $arr) {
            $arr = $this->getEnrichedData('event', $arr);
            $this->output->prepareData('event', $arr);
        }

        // 'errors' zu eigenen Objekten machen
        $allerrors = $this->cleanUpCollectionAndGet('errors');
        foreach($allerrors as $arr) {
            $arr = $this->getEnrichedData('error', $arr);
            $this->output->prepareData('error', $arr);
        }

        $this->datacollection->put('stats', [
            'sql' => $sqlStats,
        ]);

        $arr = $this->getEnrichedData('request', $this->datacollection->toArray());
        $this->output->prepareData('request', $arr);
        $this->output->send();
//        $this->sendPrepared();
    }

    private function registerSingletons() {
        app()->singleton('pklaralog', function () {
            return collect([
                'time'              => self::currentTS(),
                'uuid'              => $this->uuid,
                'appurl'            => config('app.url'),
                'appname'           => config('app.name'),
                'counter'           => collect(),
                'request'           => collect(),
                'sql'               => collect(),
                'events'            => collect(),
                'cacheevents'       => collect(),
                'lastmodel'         => '',
                'allevents'         => collect(),
                'errors'            => collect()
            ]);
        });
        return resolve('pklaralog');
    }

    private function getEnrichedData(string $type, array $json): array {
        $enricherClasses = config("laralog.enrichers.$type");

        if(is_array($enricherClasses) && count($enricherClasses)) {
            // run registered enrichers
            foreach(config("laralog.enrichers.$type") as $enricherClass) {
                /** @var ILaralogEnricher $enricher */
                $enricher = new $enricherClass();
                $extraData = $enricher->enrichFrom($json);
                $json = array_merge($json, $extraData);
            }
        }

        return $json;
    }

    private function registerListeners() {
        Event::listen('*', function($eventName, $data) {
            $caller = $this->getCaller();

            $eventNameOrig = $eventName;

            $infos     = explode(': ', $eventName);
            $eventName = $infos[0];
            $key       = $infos[1] ?? '';

            $this->incCounter($eventName);

            if($this->datacollection->get('allevents') === NULL)
                return;

            switch($eventName) {

                // query speichern
                case 'Illuminate\Database\Events\QueryExecuted':
                    $this->saveQuery($data[0]);
                    break;

                // cache abfragen speichern
                case 'Illuminate\Cache\Events\CacheHit':
                case 'Illuminate\Cache\Events\CacheMissed':
                case 'Illuminate\Cache\Events\KeyWritten':
                case 'Illuminate\Cache\Events\KeyForgotten':
                    $this->incCounter('cache'); // sum of all Cache-Events
                    $this->datacollection->get('cacheevents')->push([
                        'time' => self::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'key' => $data[0]->key
                    ]);
                    break;

                // ignorieren
                case 'Illuminate\Database\Events\StatementPrepared':
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

                case 'OwenIt\Auditing\Events\Audited':
                    $this->datacollection->get('allevents')->push([
                        'time' => self::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'eventorig' => $eventNameOrig,
                        'data'  => [
                            'user' => ['id' => $data[0]->audit->user_id],
                            'auditable_type' => $data[0]->audit->auditable_type,
                            'auditable_id' => $data[0]->audit->auditable_id,
                            'event' => $data[0]->audit->event,
                            'ip_address' => $data[0]->audit->ip_address,
                        ],
                    ]);
                    break;

                case 'Illuminate\Auth\Events\Authenticated':
                case 'App\Events\ApplicationCreatedEvent':

                    $esData = [];

                    if(isset($data[0]->user))
                        $esData['user'] = $data[0]->user->only(['id']);

                    if(isset($data[0]->event))
                        $esData['event'] = $data[0]->event->only(['id']);

                    if(isset($data[0]->application))
                        $esData['application'] = $data[0]->application->only(['id', 'eventtimes_id', 'type']);

                    $this->datacollection->get('allevents')->push([
                        'time' => self::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'eventorig' => $eventNameOrig,
                        'data'  => $esData,
                    ]);
                    break;

                // benötigt als info für sql abfragen
                case 'eloquent.booted':
                    $this->datacollection->put('lastmodel', get_class($data[0]));
                    break;

                default:
                    $this->datacollection->get('allevents')->push([
                        'time' => self::currentTS(),
                        'caller' => $caller,
                        'event' => $eventName,
                        'eventorig' => $eventNameOrig,
                        // 'data'  => $data[0],
                        'key' => $key
                    ]);
            }
        });
    }

    private function incCounter($name) {
        $old = $this->datacollection->get('counter')->get($name) ?? 0;
        $this->datacollection->get('counter')->put($name, $old + 1);
    }

    private function saveQuery($data) {
        $this->datacollection->get('sql')->push([
            'time'       => self::currentTS(),
            'sql'        => $data->sql,
            'bindingsorig' => (array) $data->bindings,
            'queryType'  => strtoupper(Arr::first(explode(' ', $data->sql))),
            'duration'   => floatval($data->time),
            'caller'     => $this->getCaller(),
            'model'      => $this->datacollection->get('lastmodel'),
            'hash'       => $this->datacollection->get('hash'),
        ]);
    }

    private function saveRequestToSingleton(Request $request) {
        $req = $this->datacollection->get('request');

        if(Auth::user() != null) {
            $req->put('user', [
                'id'       => $request->user()->id,
                'username' => $request->user()->username,
            ]);
        }

        $req->put('host', $request->getHttpHost());
        $req->put('uri', $request->server('REQUEST_URI'));
        $req->put('route', $request->route() !== NULL ? $request->route()->uri() : 'unknown');
        $req->put('method', $request->getMethod());
        $req->put('headers', Arr::only($this->headersToArray($request->headers), ['host', 'user-agent']));
        $req->put('ip', $request->header('X-Forwarded-For') ?? $request->getClientIp());
    }

    private function cleanUpCollectionAndGet($key) {
        if($this->datacollection->has('all')) {
            $all = $this->datacollection->get('all');
            if($all->count() > 0)
                Log::info("unhandled events: ".$all);
        }

        $this->datacollection->forget('lastmodel');
        $this->datacollection->forget('all');

        $data = $this->datacollection->get($key);
        $this->datacollection->forget($key);

        // gemeinsame Werte überall hin kopieren
        return $data->map(function($obj) {
            $obj['hash']    = $this->datacollection->get('hash');
            $obj['appname'] = $this->datacollection->get('appname');
            $obj['appurl']  = $this->datacollection->get('appurl');
            $obj['request'] = [
                'uri'    => $this->datacollection->get('request')->get('uri'),
                'host'   => $this->datacollection->get('request')->get('host'),
                'route'  => $this->datacollection->get('request')->get('route'),
                'method' => $this->datacollection->get('request')->get('method'),
                'user'   => $this->datacollection->get('request')->get('user'),
            ];
            return $obj;
        })->toArray();
    }

    /**
     * @param \Symfony\Component\HttpFoundation\HeaderBag $headers
     * @return array
     */
    private function headersToArray($headers) {
        $ret = [];
        foreach($headers->getIterator() as $key => $value) {
            $ret = array_merge($ret, [$key => $value[0]]);
        }
        return $ret;
    }

    public function getCaller() {
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

    private function getBacktrace() {
        return collect(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS))->map(function($a) {
            return (object) $a;
        });
    }
}
