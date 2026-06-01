<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\Form;

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
            'handle'      => $props['handle'] ?? '',
            'id'          => $props['id'] ?? 0,
            'title'       => $props['title'] ?? '',
            'description' => $props['description'] ?? ''
        ];
    }
}