<?php

namespace PKeidel\laralog\src\Enrichers;


interface ILaralogEnricher {

    public function enrichFrom(array $data): array;

}
