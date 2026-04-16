<?php 

namespace MM\Meros\App\Support\Fields;

/**
 * Represents a single row within a repeater field.
 * Responsible for preparing field instances and data only.
 */
class RepeaterRow {
    public function __construct(
        protected RepeaterTable $repeater,
        protected int           $index,
        protected array         $rowData
    ) {}

    /**
     * Gets or creates a field instance for a given sub-item name within the repeater row.
     *
     * @param string $name
     *
     * @return Field|null
     */
    public function makeField(string $name): ?Field {
        $subItem = $this->repeater->registrar->getItemByName($name);

        if (!$subItem) {
            return null;
        }

        if ($subItem->field === null) {
            $subItem->field(); // Make a field if it doesn't exist.
        }

        if ($subItem->field === null) {
            return null; // Still no field, return null.
        }

        $field = clone $subItem->field;

        $field->id = $field->id 
            ? "{$field->id}_{$this->index}" 
            : "{$name}_{$this->index}";

        $field->name  = $field->getFieldName($this->index);
        $field->value = data_get($this->rowData, $name);

        return $field;
    }

    /**
     * Gets field instances for multiple sub-item names within the repeater row.
     *
     * @param array $names
     *
     * @return Field[]
     */
    public function getFields(array $names): array {
        $fields = [];

        foreach ($names as $name) {
            $field = $this->makeField($name);

            if ($field !== null) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Gets all row data.
     *
     * @return array
     */
    public function getData(): array {
        return $this->rowData;
    }

    /**
     * Gets the index of the repeater row.
     *
     * @return int
     */
    public function getIndex(): int {
        return $this->index;
    }
}