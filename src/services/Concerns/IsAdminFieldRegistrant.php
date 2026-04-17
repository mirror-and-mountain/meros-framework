<?php 

namespace MM\Meros\Services\Concerns;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Field;
use MM\Meros\Services\Contracts\AdminFieldRegistrant;

use MM\Meros\App\Support\Fields\DataFields\Resolver;

/***************************************************************
 * Should be used in conjuction with the IsDataRegistrant trait.
 ***************************************************************/

trait IsAdminFieldRegistrant {
    // The field instance associated with this item.
    protected ?Field $field = null;

    /**
     * Adds a field to the item.
     *
     * @param  string|null  $type     The type of field to add (e.g. 'text', 'checkbox', etc.).
     * @param  array        $config   Optional configuration for the field.
     * @param  array        $args     Additional arguments for the field. Not used by default, but may be used in child overrides of this method.
     * 
     * @throws \BadMethodCallException If the item is not compatible with fields.
     * 
     * @return Field The created Field instance.
     */
    public function field(?string $type = null, array $config = [], array $args = []): Field {
        if ($this->field !== null && $this->field instanceof Field) {
            return $this->field;
        }

        if (!$this->compatibleWithField()) {
            throw new \BadMethodCallException("Item '{$this->name}' is not compatible with fields.");
        }

        // Reslove field type
        $fieldKey = $type ?? $this->inferFieldType();

        // Ensure array fields with scalar items are treated as multi-selects
        if ($fieldKey === 'select' && 
            $this->getDataType() === 'array' && 
            $this->getItemDataType() !== 'object'
        ){
            $config['multiple'] = true;
            $config['advanced'] = true;
            $config['options']  = $this->configureMultiSelectOptions($config['options'] ?? []);
        }

        $field = $this->resolveFieldType($fieldKey, $config);

        if ($config !== []) {
            $field->configure($config);
        }

        $this->field = $field;
        return $field;
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
     * Checks if the item is compatible with having a field added to it.
     *
     * @return boolean
     */
    protected function compatibleWithField(): bool {
        $compatibleTypes = ['string', 'boolean', 'integer', 'number', 'array'];

        return in_array($this->getDataType(), $compatibleTypes);
    }

    /**
     * Applies a callback function to fields that meet a certain condition.
     *
     * @param  callable $condition A function that takes a Field instance and returns a boolean indicating whether the callback should be applied to that field.
     * @param  callable $callback  The function to apply to fields that meet the condition.
     *
     * @return self
     */
    public function fieldsWhere(callable $condition, callable $callback): self {
        $this->walkFields(function ($field) use ($condition, $callback) {
            if ($condition($field)) {
                $callback($field);
            }
        });

        return $this;
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

            if ($item->field !== null) {
                $callback($item->field);
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
        $dataType = $this->getDataType() ?? 'string';

        if ($dataType === 'array' && $this->getItemDataType() === 'object') {
            return 'repeater';
        }

        if ($dataType === 'array' && $this->getItemDataType() !== null) {
            return 'select';
        }

        return match ($dataType) {
            'string'            => 'text',
            'boolean'           => 'checkbox',
            'integer', 'number' => 'number',
            default => 'text'
        };
    }

    /**
     * Returns the resolved Field instance
     * 
     * @param string $type The field type to resolve (e.g. 'text', 'checkbox', etc.). If not provided, the default field type will be used.
     * @param array  $config Optional configuration array to apply to the field.
     *
     * @return Field The resolved Field instance.
     */
    public function resolveFieldType(string $type, array $config = []): Field {
        $field = Resolver::resolve($type, $this, $this->source);

        if (!empty($config)) {
            $field->configure($config);
        }

        return $field;
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

            $type     = $item->getDataType();
            $itemType = $item->getItemDataType();

            $resolvedType = match (true) {
                $type === 'array' && $itemType === 'object' => 'array.object',
                $type === 'array' && $itemType !== null     => 'array.scalar',
                default                                     => $type,
            };

            // OBJECT - recurse only
            if ($type === 'object') {
                $this->applyAutoFields($item, $map);
                continue;
            }

            // ARRAY
            if ($type === 'array') {
                $config    = [];
                $fieldType = $map[$resolvedType] ?? ($resolvedType === 'array.object' ? 'repeater' : 'select');

                // For arrays of scalars, ensure a multi-select field with advanced mode enabled
                if ($resolvedType === 'array.scalar') {
                    $config = [
                        'multiple' => true,
                        'advanced' => true,
                        'options'  => $item->args['default'] ?? []
                    ];
                }
                    

                $item->field($fieldType, $config);

                // Recurse for array of objects
                if ($resolvedType === 'array.object') {
                    $this->applyAutoFields($item, $map);
                }

                continue;
            }

            // SCALAR
            $fieldType = $map[$resolvedType] ?? null;
            $item->field($fieldType);
        }
    }

    protected function configureMultiSelectOptions(array $options): array {
        $options = array_values(array_unique(array_merge(
            $options,
            $this->getValue() ?? []
        )));

        $clean = [];

        foreach ($options as $key => $option) {
            if (!is_string($option)) {
                continue; // Skip non-string options
            }

            if (is_int($key)) {
                $clean[Str::slug($option)] = Str::title(Str::replace(['-', '_'], ' ', $option));
            } 
            
            else {
                $clean[Str::slug($key)] = Str::title(Str::replace(['-', '_'], ' ', $option));
            }
        }
        
        return $clean;
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