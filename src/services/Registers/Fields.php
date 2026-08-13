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

    /**
     * Retrieves a field by its name.
     *
     * @param string $fieldName
     *
     * @return Field|null
     */
    public function getByName(string $fieldName): ?Field {
        $field = $this->instances->where('name', $fieldName)->first();
        return $field ?: null;
    }

    /**
     * Retrieves a field by its ID.
     *
     * @param string $fieldId
     *
     * @return Field|null
     */
    public function getById(string $fieldId): ?Field {
        $field = $this->instances->where('id', $fieldId)->first();
        return $field ?: null;
    }
}