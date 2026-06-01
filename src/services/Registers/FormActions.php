<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\FormAction;

class FormActions extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FormAction::class;

    /**
     * Parses the properties for the form action's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return [
            'handle'      => $props['handle'] ?? '',
            'label'       => $props['label'] ?? '',
            'description' => $props['description'] ?? '',
        ];
    }
}