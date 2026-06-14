<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\Form;

class Forms extends Register {
    protected string $identifier = 'id';
    protected string $definition = Form::class;
    protected array  $rejects    = ['makeFromCallback'];

    /**
     * Parses properties for the form's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // No special parsing needed for forms at this time.
    }
}