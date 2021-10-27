<?php

namespace PKeidel\Laralog\Outputs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class TelegrafOutput implements IOutput
{
    private string $host;
    private int $port;
    private $sock;

    public function __construct()
    {
        $this->host = config("laralog.telegraf.host");
        $this->port = config("laralog.telegraf.port");
        $this->sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    public function prepareData(string $type, array $data)
    {
        $msg = "$type,appurl='" . env('APP_URL') . "',route='" . $data['request']['route'] . "',reqId=" . $data['reqId'];
        $send = true;

        // telegraf
        switch ($type) {
            case 'request':
                $flattened = Arr::dot($data['stats']);

                array_walk($flattened, function($val, $key) use(&$result) {
                    $result .= ",$key=$val";
                });

                $msg .= " duration=" . $data['duration'] . (isset($result) ? $result : '');
                break;
            case 'sql':
                $msg .= " duration=" . $data['duration'];
                break;
            default:
                $send = false;
        }

        $len = strlen($msg);
        if (!$send) return;

        // https://github.com/influxdata/telegraf/tree/master/plugins/inputs/socket_listener
//        $bytes = socket_sendto($this->sock, $msg, $len, 0, $this->host, $this->port);
        Log::info("Telegraf: $msg");
    }

    public function send()
    {
        socket_close($this->sock);
    }
}
