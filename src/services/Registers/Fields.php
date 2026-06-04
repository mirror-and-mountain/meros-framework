<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\Field;

class Fields extends Register {
    protected string $identifier = 'handle';
    protected string $definition = Field::class;
    protected array  $rejects    = ['make', 'makeFromCallback'];

    /**
     * Parses properties for the field's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // No special parsing needed for fields at this time.
    }
}