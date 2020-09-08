<?php

namespace PKeidel\Laralog\Outputs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

class ElasticsearchOutput implements IOutput {

    private $esurl;
    private $esuser;
    private $espassword;
    private $espipeline;
    private $esbulkurlappendix = '';
    private $bulk = '';

    public function __construct() {
        $this->esurl = config("laralog.elasticsearch.url");
        $this->esuser = config("laralog.elasticsearch.username");
        $this->espassword = config("laralog.elasticsearch.password");
        $this->espipeline = config("laralog.elasticsearch.pipeline");

        if(($pipeline = config("laralog.elasticsearch.pipeline")) !== NULL) {
            $this->esbulkurlappendix = "?pipeline=$pipeline";
        }
    }

    private function getIndexName() {
        $index = config("laralog.elasticsearch.index");

        if(is_callable($index)) {
            $index = call_user_func($index);
        }
        return preg_replace("/[^0-9a-z-.]/", "", $index);
    }

    public function prepareData(string $type, array $data, string $uuid) {
        $data['type'] = $type;
        $data['uuid'] = $uuid;
        $index = $this->getIndexName();

        $this->bulk .= "{\"index\" : { \"_index\" : \"$index\", \"_type\" : \"_doc\"}}\n";
        $this->bulk .= json_encode($data)."\n";
    }

    public function send() {
        if(!strlen(trim($this->bulk)))
            return;

        $jsonBody = $this->sendBody('/_bulk' . $this->esbulkurlappendix, $this->bulk);
        if(!is_object($jsonBody) || $jsonBody->errors) {
            Log::error("ES URL: $this->esurl");

            if(isset($jsonBody->items)) {
                foreach($jsonBody->items as $idx => $itm) {
                    if(($itm->index->status ?? 500) !== 201) { // 201 => created
                        Log::error("$idx: ".json_encode($itm));
                    }
                }
            }
        }
        $this->bulk = '';
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
            return (object) ['errors' => true, 'items' => [$e->getMessage()]];
        }
    }

    public function delete(string $url) {
        try {
            $url = "{$this->esurl}{$url}";
            $client = new Client(['verify' => false]);
            $response = $client->delete($url, [
                RequestOptions::AUTH => [$this->esuser, $this->espassword],
                RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
            ]);

            return json_decode($response->getBody());
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
}
