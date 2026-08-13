<?php

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Integration;
use MM\Meros\Services\Contracts\Register;

class Integrations extends Register {
    protected string $identifier = 'handle';
    protected string $definition = Integration::class;

    protected function parseProperties(array $props): array {
        return $props; // No additional parsing needed for integrations
    }
}