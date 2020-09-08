<?php

namespace PKeidel\Laralog\Enrichers;


class RequestOpcacheInfoEnricher implements ILaralogEnricher {

    /**
     * Adds some opcache information to the request objects
     * @param array $data
     * @return array
     */
    public function enrichFrom(array $data): array {

        $installed = function_exists('opcache_get_status');
        
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

        $status = opcache_get_status();

        if(is_array($status)) {
            unset($status['scripts']);
            $dataObj['enriched']['opcache']['status'] = $status;
        }

        $config = opcache_get_configuration();

        if(is_array($config)) {
            $dataObj['enriched']['opcache']['config'] = $config;
        }

        return $dataObj;
    }
}
