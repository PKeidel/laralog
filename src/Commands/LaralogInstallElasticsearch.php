<?php

namespace PKeidel\laralog\src\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PKeidel\laralog\src\Outputs\ElasticsearchOutput;

class LaralogInstallElasticsearch extends Command {
    protected $signature = 'es:install';
    protected $description = 'Creates the template for the index in elasticserach and adds a Kibana Dashboard with some charts';

    private $esurl = null;
    private $esuser = null;
    private $espassword = null;
    private $uuid = null;

    public function __construct() {
        parent::__construct();
        $this->esurl = config("laralog.elasticsearch.url");
        $this->esuser = config("laralog.elasticsearch.username");
        $this->espassword = config("laralog.elasticsearch.password");
        $this->uuid = uniqid();
    }

    public function handle() {
        $pathToESMappingJson = File::exists(base_path('pkeidel/laralog/assets/es-mapping.json'))
                                   ? base_path('pkeidel/laralog/assets/es-mapping.json')
                                   : app_path('vendor/pkeidel/laralog/assets/es-mapping.json');

        $es = new ElasticsearchOutput();
        $jsonBody = $es->sendBody(File::get($pathToESMappingJson));
        $this->info($jsonBody);
    }
}
