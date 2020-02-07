<?php

namespace PKeidel\laralog\src\Outputs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

class ElasticsearchOutput implements IOutput {

    private $esurl = null;
    private $esuser = null;
    private $espassword = null;
    private $uuid = null;
    private string $bulk = '';

    public function __construct() {
        $this->esurl = config("laralog.elasticsearch.url");
        $this->esuser = config("laralog.elasticsearch.username");
        $this->espassword = config("laralog.elasticsearch.password");
        $this->uuid = uniqid();
    }

    private function getIndexName() {
        $index = config("laralog.elasticsearch.index");

        if(is_callable($index)) {
            $index = call_user_func($index);
        }
        return preg_replace("/[^0-9a-z-.]/", "", $index);
    }

    public function prepareData(string $type, array $data) {
        $data['type'] = $type;
        $data['uuid'] = $this->uuid;
        $index = $this->getIndexName();

        $this->bulk .= "{\"index\" : { \"_index\" : \"$index\", \"_type\" : \"_doc\"}}\n";
        $this->bulk .= json_encode($data)."\n";
    }

    public function send() {
        $jsonBody = $this->sendBody('/_bulk', $this->bulk);
        if(!is_object($jsonBody) || $jsonBody->errors) {
            Log::error("ES URL: $this->esurl");

            foreach($jsonBody->items as $idx => $itm) {
                if($itm->index->status !== 201) {
                    Log::error("$idx: ".json_encode($itm));
                }
            }
        }
    }

    public function sendBody(string $url, string $body) {
        try {
            $url = "{$this->esurl}{$url}";
            $client = new Client(['verify' => false]);
            $response = $client->post($url, [
                RequestOptions::AUTH => [$this->esuser, $this->espassword],
                RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
                RequestOptions::BODY => $body
            ]);

            return json_decode($response->getBody());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
