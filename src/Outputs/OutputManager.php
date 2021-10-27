<?php

namespace PKeidel\Laralog\Outputs;

class OutputManager implements IOutput
{
    /** @var IOutput[] $outputs */
    private array $outputs = [];

    public function add(IOutput $output): void {
        $this->outputs[] = $output;
    }

    public function prepareData(string $type, array $data): void {
        array_walk($this->outputs, static fn($output) => $output->prepareData($type, $data));
    }

    public function send(): void {
        array_walk($this->outputs, static fn($output) => $output->send());
    }
}
