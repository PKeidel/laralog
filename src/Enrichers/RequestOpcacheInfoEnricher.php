<?php

namespace PKeidel\Laralog\Enrichers;

class RequestOpcacheInfoEnricher implements ILaralogEnricher {
    private array $config = [];

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Adds some opcache information to the request objects
     * @param array $data
     */
    public function enrichFrom(array &$data): void {

        $installed = function_exists('opcache_get_status');

        $data['enriched'] ??= [];
        $data['enriched']['opcache'] ??= [];
        $data['enriched']['opcache']['installed'] = $installed;

        if(!$installed) {
            return;
        }

        $status = opcache_get_status();

        if(is_array($status)) {
            unset($status['scripts']);
        } elseif(is_bool($status)) {
            $data['enriched']['opcache']['status'] = $status;
        }

        $config = opcache_get_configuration();

        if(is_array($config)) {

            if(array_key_exists('directives', $this->config)) {
                $config['directives'] = array_intersect_key(
                    $config['directives'],
                    array_combine($this->config['directives'], $this->config['directives'])
                );
            }

            $data['enriched']['opcache']['config'] = $config;
        }
    }
}
