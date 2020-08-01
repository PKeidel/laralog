<?php

namespace PKeidel\laralog\src\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PKeidel\laralog\src\Outputs\ElasticsearchOutput;

class LaralogInstallElasticsearch extends Command {
    protected $signature = 'es:install';
    protected $description = 'Creates the template for the index in elasticserach and adds a Kibana Dashboard with some charts';

    private $esurl;
    private $esuser;
    private $espassword;

    public function __construct() {
        parent::__construct();
        $this->esurl = config("laralog.elasticsearch.url");
        $this->esuser = config("laralog.elasticsearch.username");
        $this->espassword = config("laralog.elasticsearch.password");
    }

    public function handle() {
        $pathToESMappingJson = File::exists(base_path(__FILE__.'/../../assets/es-mapping.json'))
                                   ? base_path(__FILE__.'/../../assets/es-mapping.json')
                                   : app_path(__FILE__.'/../../assets/es-mapping.json');

        $es = new ElasticsearchOutput();
        $es->sendBody('/_template/template_laravel', File::get($pathToESMappingJson));
    }
}
