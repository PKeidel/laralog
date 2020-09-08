<?php

namespace PKeidel\Laralog\Enrichers;


class RequestApcuInfoEnricher implements ILaralogEnricher {

    /**
     * Adds some apcu information to the request objects
     * @param array $data
     * @return array
     */
    public function enrichFrom(array $data): array {

        $installed = function_exists('apcu_cache_info');

        $dataObj = [
            'enriched' => [
                'opcache' => [
                    'installed' => $installed,
                ]
            ]
        ];

        if(!$installed) {
            return $dataObj;
        }

        $dataObj['enriched']['apcu']['enabled'] = apcu_enabled();

        $info = apcu_cache_info();

        if(is_array($info)) {
            unset($info['cache_list']);
            unset($info['slot_distribution']);
            $dataObj['enriched']['apcu']['info'] = $info;
        }

        return $dataObj;
    }
}
