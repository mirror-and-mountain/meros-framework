<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Interfaces\AdminFieldRegistrant;

use MM\Meros\Facades\Fields;

/***************************************************************
 * Should be used in conjunction with the IsDataRegistrant trait.
 ***************************************************************/

trait IsAdminFieldRegistrant {
    /**
     * The field instance associated with this registrant, if any.
     *
     * @var Field|null
     */
    protected ?Field $field = null;

    abstract public function provider(): FeatureProvider;
    abstract public function getDataType(bool $arrayTypes = false): string;

    // =========================================================================
    // Field Management
    // =========================================================================

    /**
     * Creates or retrieves the field instance associated with this registrant. 
     * If a field already exists, it will return that instance and optionally apply a callback to it. 
     * 
     * If no field exists, it will create one and optionally apply a callback to it.
     * If no field type if provided, one will be inferred based on the registrant's data type.
     *
     * @param string|null  $type
     * @param Closure|null $callback
     * @param array        $args Additional arguments (intended for SettingsFieldsOnly via the Setting Contract)
     *
     * @return Field
     */
    public function field(?string $type = null, ?Closure $callback = null, array $args = []): Field {
        // If a field already exists, return it and optionally apply the callback
        if ($this->field !== null && $this->field instanceof Field) {
            if ($callback instanceof Closure) {
                $callback($this->field);
            }

            return $this->field;
        }

        // Check the compatibility of the registrant with fields
        if (!$this->compatibleWithField()) {
            throw new \BadMethodCallException("Registrant '{$this->name}' is not compatible with fields.");
        }

        // Resolve field type
        $fieldKey = $type ?? $this->inferFieldType();

        // Check register for id e.g. 'text'
        $this->field = $this->makeFieldFrom($fieldKey, $callback);

        if (!$this->field->isCompatibleWith($this->getDataType(true))) {
            throw new \InvalidArgumentException("Field of type '{$fieldKey}' is not compatible with data type '{$this->getDataType(true)}'.");
        }

        $this->field->dataType($this->getDataType()); // Set the data type

        return $this->field;
    }

    /**
     * Checks if the item is compatible with having a field added to it.
     *
     * @return boolean
     */
    protected function compatibleWithField(): bool {
        $compatibleTypes = ['string', 'boolean', 'integer', 'number', 'array'];

        return in_array($this->getDataType(), $compatibleTypes);
    }

    /**
     * Infers the item's field type.
     *
     * @return string
     */
    protected function inferFieldType(): string {
        $dataType = $this->getDataType(true) ?? 'string';

        return match ($dataType) {
            'array.object'      => 'repeater',
            'array.scalar'      => 'multi_select',
            'string'            => 'text',
            'boolean'           => 'checkbox',
            'integer', 'number' => 'number',
            default => 'text'
        };
    }

    /**
     * Creates a field instance from the register based on the provided class or ID.
     *
     * @param string        $type
     * @param Closure|null  $callback
     *
     * @return Field
     */
    protected function makeFieldFrom(string $type, ?Closure $callback = null): Field {
        $field = Fields::checkout($this->provider())->makeFrom($type, $callback, [
            'id'        => $this->getID(),
            'name'      => $this->getName(),
            'default'   => $this->args['default'] ?? null,
        ]);

        $field->rootName($this->getRootName());
        $field->class('meros-admin-field');

        return $field;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Applies a callback function to all fields associated with the item.
     *
     * @param  callable $callback
     *
     * @return void
     */
    protected function walkFields(callable $callback): void {
        foreach ($this->subItems as $item) {

            $field = $item->getField();

            if ($field !== null) {
                $callback($field);
            }

            if (!empty($item->subItems)) {
                $item->walkFields($callback);
            }
        }
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the field ID.
     *
     * @return string
     */
    public function getID(): string {
        return $this->name . '_field';
    }

    /**
     * Returns the field name.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the root name for the field
     * 
     * @return string
     */
    public function getRootName(): string {
        $segments = explode('.', $this->path);
        $name     = '';

        foreach ($segments as $segment) {
            if ($segment === array_first($segments)) {
                $name .= $segment;
            } else if ($segment !== $this->name) {
                $name .= '[' . $segment . ']';
            }

            if ($segment === $this->name) {
                break;
            }
        }

        return $name;
    }

    /**
     * Returns the field label. If no label is explicitly set, it generates one from the name.
     *
     * @return string
     */
    public function getLabel(): string {
        if (!empty($this->args['label'])) {
            return $this->args['label'];
        }

        $this->args['label'] = Str::title(Str::replace(['_', '-'], ' ', $this->name));
        return $this->args['label'];
    }

    /**
     * Returns the field description.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->args['description'] ?? '';
    }

    /**
     * Returns sub-item names for array-type items, used for repeaters and arrays of objects.
     *
     * @return array
     */
    public function getItemNames(): array {
        return array_map(
            fn($item) => $item->name,
            $this->subItems
        );
    }

    /**
     * Returns sub-item labels for array-type items, used for repeaters and arrays of objects.
     *
     * @return array
     */
    public function getItemLabels(): array {
        return array_map(
            fn($item) => $item->getLabel(),
            $this->subItems
        );
    }

    /**
     * Returns a sub-item by its name.
     *
     * @param string $name
     * 
     * @return AdminFieldRegistrant|null
     */
    public function getItemByName(string $name): ?AdminFieldRegistrant {
        return collect($this->subItems)
            ->firstWhere('name', $name);
    }

    /**
     * Returns field names for all fields associated with the item and its sub-items.
     *
     * @return array
     */
    public function getFieldNames(): array {
        $names = [];

        $this->walkFields(function(Field $field) use (&$names) {
            $names[] = $field->getName();
        });

        return $names;
    }

    /**
     * Returns all field instances associated with the item and its sub-items.
     *
     * @return array
     */
    protected function getChildFields(): array {
        $fields = [];

        $this->walkFields(function(Field $field) use (&$fields) {
            $fields[] = $field;
        });

        return $fields;
    }

    /**
     * Returns the field instance directly associated with the item, if any.
     *
     * @return Field|null
     */
    protected function getField(): ?Field {
        return $this->field instanceof Field ? $this->field : null;
    }
}