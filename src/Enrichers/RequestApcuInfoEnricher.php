<?php

namespace PKeidel\Laralog\Enrichers;

class RequestApcuInfoEnricher implements ILaralogEnricher {

    /**
     * Adds some apcu information to the request objects
     * @param array $data
     */
    public function enrichFrom(array &$data): void {

        $installed = function_exists('apcu_cache_info');

        $data['enriched'] ??= [];
        $data['enriched']['apcu'] ??= [];
        $data['enriched']['apcu']['installed'] = $installed;

        if(!$installed) {
            return;
        }

        $data['enriched']['apcu']['enabled'] = apcu_enabled();

        $info = apcu_cache_info();

        if(is_array($info)) {
            unset($info['cache_list'], $info['slot_distribution']);
            $data['enriched']['apcu']['info'] = $info;
        }
    }
}
