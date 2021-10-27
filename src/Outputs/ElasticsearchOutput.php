<?php

namespace PKeidel\Laralog\Outputs;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

class ElasticsearchOutput implements IOutput {

    private string $esurl;
    private string $esuser;
    private string $espassword;
    private string $esbulkurlappendix = '';
    private string $bulk = '';
    private bool $verifyssl;
    private array $config;

    public function __construct(array $config = []) {
        $this->esurl = config("laralog.elasticsearch.url");
        $this->esuser = config("laralog.elasticsearch.username");
        $this->espassword = config("laralog.elasticsearch.password");
        $this->verifyssl = config("laralog.elasticsearch.verifyssl", true);

        if(($pipeline = config("laralog.elasticsearch.pipeline")) !== NULL) {
            $this->esbulkurlappendix = "?pipeline=$pipeline";
        }
        $this->config = $config;
    }

    private function getIndexName(): string {
        $index = config("laralog.elasticsearch.index");

        if(is_callable($index)) {
            $index = $index();
        }
        return $index;
    }

    public function prepareData(string $type, array $data): void {

        // if we shouldn't log this type, just skip it
        if (
            array_key_exists('only', $this->config) &&
            !in_array($type, $this->config['only'] ?? [], true)
        ) {
            return;
        }

        $data['type'] = $type;
        $index = $this->getIndexName();

        $this->bulk .= "{\"create\" : { \"_index\" : \"$index\"}}\n";
        $this->bulk .= json_encode($data)."\n";
    }

    public function send(): void {
        if(trim($this->bulk) === '') {
            return;
        }

        $jsonBody = $this->sendBody('/_bulk' . $this->esbulkurlappendix, $this->bulk);
        if(!is_object($jsonBody) || $jsonBody->errors) {
            if(isset($jsonBody->items)) {
                foreach($jsonBody->items as $idx => $itm) {
                    if(($itm->create->status ?? 500) !== 201) { // 201 => created
                        Log::error("$idx: " . json_encode($itm));
                    }
                }
            }
        }
        $this->bulk = '';
    }

    public function sendBody(string $url, string $body): object {
        try {
            $url = "$this->esurl$url";
            $client = new Client(['verify' => $this->verifyssl]);
            $response = $client->post($url, [
                RequestOptions::AUTH => [$this->esuser, $this->espassword],
                RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
                RequestOptions::BODY => $body
            ]);

            return json_decode($response->getBody());
        } catch (\Throwable $t) {
            Log::error(get_class($t) . ': ' . $t->getMessage());
            return (object) ['errors' => true, 'items' => [$t->getMessage()]];
        }
    }

    public function delete(string $url): void {
        try {
            $url = "$this->esurl$url";
            $client = new Client(['verify' => $this->verifyssl]);
            $client->delete($url, [
                RequestOptions::AUTH => [$this->esuser, $this->espassword],
                RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
            ]);
        } catch (\Throwable $t) {
            Log::error(get_class($t) . ': ' . $t->getMessage());
        }
    }
}
