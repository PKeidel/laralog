<?php

namespace PKeidel\Laralog\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PKeidel\Laralog\Outputs\ElasticsearchOutput;

class LaralogInstallElasticsearch extends Command {
    protected $signature = 'laralog:es:install';
    protected $description = 'Creates the template for the index in elasticserach and adds a Kibana Dashboard with some charts';

    public function handle() {
        $pathToESMappingJson = File::exists($publishedPath = base_path('storage/laralog-es-mapping.json'))
                                   ? $publishedPath
                                   : __DIR__.'/../../assets/es-mapping.json';
        $pathToESMappingJson = realpath($pathToESMappingJson);

        $es  = new ElasticsearchOutput();
        $this->info("Deleting existing template ...");
        $es->delete('/_template/template_laravel');
        $this->info("Creating new template ...");
        $es->sendBody('/_template/template_laravel', File::get($pathToESMappingJson));

        /*
        curl -s -X POST http://opensearch-dashboards:5601/api/saved_objects/index-pattern/8b279580-19ab-11e9-a835-3d41de572032?overwrite=true -H 'osd-xsrf: true' -H 'Content-Type: application/json' -d '
        {
          "attributes": {
            "title": "laravel-*",
            "timeFieldName": "time"
          }
        }' > /dev/null

        curl -s -X POST http://opensearch-dashboards:5601/api/kibana/dashboards/import -H 'osd-xsrf: true' -H 'Content-Type: application/json' -d @kibana_dumps/"${filename}" > /dev/null
         */
    }
}
