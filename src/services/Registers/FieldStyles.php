<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\FieldStyle;
use MM\Meros\Services\Contracts\Register;

class FieldStyles extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FieldStyle::class;

    /**
     * Parses properties for the field group's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'handle' => $props['handle'] ?? '',
            'view'   => $props['view'] ?? '',
        ];
    }
}