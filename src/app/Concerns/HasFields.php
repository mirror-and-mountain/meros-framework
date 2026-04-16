<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Str;

use MM\Meros\App\Contracts\FieldRegistrar;
use MM\Meros\App\Support\Fields\Field;
use MM\Meros\App\Support\Fields\FieldResolver;

/***************************************************************
 * Should be used in conjuction with the HasDataBuilder trait.
 ***************************************************************/

trait HasFields {
    // The field instance associated with this item.
    public ?Field $field = null;

    /**
     * Adds a field to the item.
     *
     * @param  string|null  $type     The type of field to add (e.g. 'text', 'checkbox', etc.).
     * @param  array        $config   Optional configuration for the field.
     * @param  array        $args     Additional arguments for the field. Not used by default, but may be used in child overrides of this method.
     * 
     * @return Field The created Field instance.
     */
    public function field(?string $type = null, array $config = [], array $args = []): Field {
        if ($this->field !== null) {
            throw new \Exception("Item '{$this->name}' already has a field.");
        }

        if (!$this->compatibleWithField()) {
            throw new \Exception("Item '{$this->name}' is not compatible with fields.");
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
        $field = FieldResolver::resolve($type, $this, $this->source);

        if (!empty($config)) {
            $field->configure($config);
        }

        return $field;
    }

    /**
     * Recursively applies auto field generation to compatible items.
     *
     * @param  FieldRegistrar $registrar
     * @param  array $map Optional mapping of setting paths to field types (e.g. ['address.street' => 'text']).
     *
     * @return void
     */
    protected function applyAutoFields(FieldRegistrar $registrar, array $map = []): void {
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

    // The field ID
    public function getFieldID(): string {
        return $this->name . '_field';
    }

    // The field name
    public function getFieldName(): string {
        return $this->name;
    }

    // The field label
    public function getFieldLabel(): string {
        if (!empty($this->args['label'])) {
            return $this->args['label'];
        }

        $this->args['label'] = Str::title(Str::replace(['_', '-'], ' ', $this->name));
        return $this->args['label'];
    }

    // The field description
    public function getFieldDescription(): string {
        return $this->args['description'] ?? '';
    }

    // Sub-item names for repeaters
    public function getItemNames(): array {
        return array_map(
            fn($item) => $item->name,
            $this->subItems
        );
    }

    // Sub-item labels for repeaters
    public function getItemLabels(): array {
        return array_map(
            fn($item) => $item->getFieldLabel(),
            $this->subItems
        );
    }

    // Get sub-item by name
    public function getItemByName(string $name): ?FieldRegistrar {
        return collect($this->subItems)
            ->firstWhere('name', $name);
    }

    // Field names for nested fields
    public function getFieldNames(): array {
        $names = [];

        $this->walkFields(function(Field $field) use (&$names) {
            $names[] = $field->getFieldName();
        });

        return $names;
    }
}