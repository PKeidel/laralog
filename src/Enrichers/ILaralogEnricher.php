<?php

namespace PKeidel\Laralog\Enrichers;


interface ILaralogEnricher {

    public function enrichFrom(array $data): array;

}
