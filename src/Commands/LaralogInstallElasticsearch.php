<?php

namespace PKeidel\Laralog\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PKeidel\Laralog\Outputs\ElasticsearchOutput;

class LaralogInstallElasticsearch extends Command {
    protected $signature = 'laralog:es:install';
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
        $pathToESMappingJson = File::exists(base_path('storage/es-mapping.json'))
                                   ? base_path('storage/es-mapping.json')
                                   : __DIR__.'/../../assets/es-mapping.json';
        $pathToESMappingJson = realpath($pathToESMappingJson);

        $es  = new ElasticsearchOutput();
        $this->info("Deleting existing template ...");
        $es->delete('_template/template_laravel');
        $this->info("Creating new template ...");
        $es->sendBody('_template/template_laravel', File::get($pathToESMappingJson));
    }
}
