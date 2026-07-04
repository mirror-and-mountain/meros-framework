<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Interfaces\AdminFieldRegistrant;

use MM\Meros\Facades\Fields;
use MM\Meros\App\Fields\Repeater;

/***************************************************************
 * Should be used in conjunction with the IsDataRegistrant trait.
 ***************************************************************/

trait IsAdminFieldRegistrant {
    /**
     * The field instance associated with this registrant, if any.
     *
     * @var Field|string|null
     */
    protected Field|string|null $field = null;

    /**
     * Adds a field to the registrant.
     *
    * @param Field|string|null  $type     The type of field to add (e.g. 'text', 'checkbox', etc.), a Field instance, a Field class name, or null to infer the field type.
    * @param Closure|array|null $callback Optional callback to configure the field, or props array for legacy calls.
    * @param array              $props    Optional properties for the field.
    * @param array              $args     Additional arguments for child overrides. Not used by default.
     * 
     * @return Field The created Field instance.
     * @throws \BadMethodCallException if the registrant is not compatible with fields.
     * @throws \InvalidArgumentException if the provided field type is not compatible with the registrant's data type.
     */
    public function field(Field|string|null $type = null, Closure|array|null $callback = null, array $props = [], array $args = []): Field {
        $params = func_num_args();

        // Legacy signature support: field($type, $props, $args)
        if ($params >= 3 && is_array($callback)) {
            $legacyProps = $callback;

            if ($params === 2) {
                $props = $legacyProps;
                $callback = null;
            }

            else if ($params === 3 && is_array($props)) {
                $args = $props;
                $props = $legacyProps;
                $callback = null;
            }
        }

        if ($this->field !== null && $this->field instanceof Field) {
            if ($callback instanceof Closure) {
                $callback($this->field);
            }

            return $this->field;
        }

        if (!$this->compatibleWithField()) {
            throw new \BadMethodCallException("Registrant '{$this->name}' is not compatible with fields.");
        }

        // Attach an existing instance
        if ($type instanceof Field) {

            if (!$type->isCompatibleWith($this->getDataType(true))) {
                throw new \InvalidArgumentException("Field of type '{$type->handle}' is not compatible with data type '{$this->getDataType(true)}'.");
            }

            $this->field = $type;
            $this->field->dataType($this->getDataType()); // Set the data type

            return $this->field;
        }

        // Instantiate a new field from a class name
        if (Str::contains($type, '\\')) {
            $this->field = $this->makeFieldFrom($type, $callback, $props);

            if (!$this->field->isCompatibleWith($this->getDataType(true))) {
                throw new \InvalidArgumentException("Field of type '{$type}' is not compatible with data type '{$this->getDataType(true)}'.");
            }

            $this->addRepeaterFields(); // If it's a repeater, add child fields for any compatible sub-items
            $this->field->dataType($this->getDataType()); // Set the data type
            return $this->field;
        }

        // Resolve field type
        $fieldKey = $type ?? $this->inferFieldType();

        // Check register for id e.g. 'text'
        $this->field = $this->makeFieldFrom($fieldKey, $callback, $props);

        if (!$this->field->isCompatibleWith($this->getDataType(true))) {
            throw new \InvalidArgumentException("Field of type '{$fieldKey}' is not compatible with data type '{$this->getDataType(true)}'.");
        }

        $this->addRepeaterFields(); // If the field is a repeater, add child fields for any compatible sub-items
        $this->field->dataType($this->getDataType()); // Set the data type

        return $this->field;
    }

    /**
     * Automatically generates fields for compatible items.
     * 
     * @param array $map Optional mapping of setting paths to field types (e.g. ['address.street' => 'text']).
     *
     * @return self
     */
    public function autoFields(array $map = []): self {
        $this->applyAutoFields($this, $map);
        return $this;
    }

    /**
     * Applies a callback function to all fields associated with the item.
     *
     * @param  callable $callback
     *
     * @return self
     */
    public function fields(callable $callback): self {
        $this->walkFields($callback);
        return $this;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Creates a field instance from a given class name or register ID.
     *
    * @param string               $classOrId
    * @param Closure|array|null   $callback
    * @param array                $props
     *
     * @return Field
     */
    protected function makeFieldFrom(string $classOrId, Closure|array|null $callback = null, array $props = []): Field {
        $field = Fields::checkout($this->provider)->makeFrom($classOrId, $callback, [
            'id'        => $this->name . '_field',
            'name'      => $this->name
        ] + $props);

        $field->rootName($this->getRootName());
        $field->class('meros-admin-field');
        return $field;
    }

    /**
     * Checks if the registrant is currently within a repeater context.
     *
     * @return boolean
     */
    protected function isInRepeater(): bool {
        if ($this->parent && $this->parent->getField() instanceof Repeater) {
            return true;
        }

        return false;
    }

    /**
     * Helper to add child fields to a repeater field for any compatible sub-items.
     *
     * @return void
     */
    protected function addRepeaterFields(): void {
        if (!$this->field instanceof Repeater) {
            return;
        }
        
        $childFields = $this->getChildFields();
        $this->field->attach($childFields);
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

    /**
     * Recursively applies auto field generation to compatible items.
     *
     * @param  AdminFieldRegistrant $registrar
     * @param  array $map Optional mapping of setting paths to field types (e.g. ['address.street' => 'text']).
     *
     * @return void
     */
    protected function applyAutoFields(AdminFieldRegistrant $registrar, array $map = []): void {
        foreach ($registrar->subItems as $item) {
            // Skip if already has a field
            if ($item->field !== null) {
                continue;
            }

            $type = $item->getDataType(true);

            // OBJECT - recurse only
            if ($type === 'object') {
                $this->applyAutoFields($item, $map);
                continue;
            }

            // ARRAY - OBJECTS
            if ($type === 'array.object') {
                $item->field('repeater');

                // Recurse for array of objects
                $this->applyAutoFields($item, $map);

                continue;
            }

            // ARRAY - SCALARS
            if ($type === 'array.scalar') {
                $item->field('multi_select');
                continue;
            }

            // SCALAR
            $item->field(); // Will use InferFieldType
        }
    }

    /***************************
     * Contract Methods
     ***************************/

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
}