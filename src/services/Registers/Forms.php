<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Form;
use MM\Meros\Services\Contracts\Register;

class Forms extends Register {
    protected string $identifier = 'id';
    protected string $definition = Form::class;

    /**
     * Parses properties for the form's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'id'          => $props['id'] ?? '',
            'title'       => $props['title'] ?? '',
            'description' => $props['description'] ?? '',
            'elements'    => $props['elements'] ?? [],
        ];
    }
}