<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Elements\FormStyle;

class FormStyles extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FormStyle::class;
    protected array  $rejects = ['make'];

    /**
     * Parses properties for the form style's constructor.
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