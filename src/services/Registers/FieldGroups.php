<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

class FieldGroups extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FieldGroup::class;
    protected array  $rejects    = ['makeFrom'];

    /**
     * Parses properties for the field group's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // No special parsing needed for field groups at this time.
    }
}