<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\FieldWrapper;

class FieldWrappers extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FieldWrapper::class;
    protected array  $rejects    = ['make', 'makeFromCallback'];

    /**
     * Parses properties for the field wrapper's constructor.
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