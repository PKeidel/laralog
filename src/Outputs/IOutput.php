<?php

namespace PKeidel\laralog\src\Outputs;

interface IOutput {

    public function prepareData(string $type, array $data);
    public function send();

}
