<?php

namespace PKeidel\Laralog\Outputs;

interface IOutput {

    public function prepareData(string $type, array $data, string $uuid);
    public function send();

}
