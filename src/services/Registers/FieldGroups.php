<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Elements\FieldGroup;

class FieldGroups extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FieldGroup::class;

    /**
     * Parses properties for the field group's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'handle'      => $props['handle'] ?? '',
            'title'       => $props['title'] ?? '',
            'description' => $props['description'] ?? '',
            'fields'      => $props['fields'] ?? [], 
        ];
    }
}