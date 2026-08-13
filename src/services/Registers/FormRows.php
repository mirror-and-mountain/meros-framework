<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\FormRow;

class FormRows extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FormRow::class;
    protected array $rejects     = ['register', 'makeFrom', 'makeFromCallback'];

    /**
     * Parses properties for the form row's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // No special parsing needed for form rows at this time.
    }
}