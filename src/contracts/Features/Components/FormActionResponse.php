<?php

namespace MM\Meros\Contracts\Features\Components;

final class FormActionResponse {
    private mixed $output;
    private bool $isError;
    private array $schema = [];

    public function __construct(FormAction $action, mixed $output, bool $isError = false) {
        $this->output  = $output;
        $this->isError = $isError;
        $this->schema  = $action::getOutputSchema();
    }

    public function output(): mixed {
        if (is_array($this->output) && $this->schema !== []) {
            $this->output = $this->validateOutput();
        }

        return $this->output;
    }

    private function validateOutput(): array {
        $output = $this->output;
        $validatedOutput = [];
        $defaults = [];

        foreach ($this->schema as $key => $value) {
            if (is_array($value) && array_key_exists('default', $value)) {
                $defaults[$key] = $value;
            }
        }

        $output = array_merge($defaults, $output);

        foreach ($output as $key => $value) {
            if (!array_key_exists($key, $this->schema)) {
                continue;
            }

            if (!is_array($this->schema[$key]) || 
                !array_key_exists('type', $this->schema[$key])
            ) {
                continue;
            }

            $requiredType = $this->schema[$key]['type'];
            $type = gettype($value);

            if ($type !== $requiredType) {
                continue;
            }

            $validatedOutput[$key] = $value;
        }

        return $validatedOutput;
    }

    public function hasError(): string|false {
        if ($this->isError) {
            return $this->output;
        }

        return false;
    }
}