<?php 

namespace MM\Meros\App\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\App\Contracts\FieldRegistrar;
use MM\Meros\App\Support\Field;

trait HasFields {
    protected string $fieldClass; // Must be set in the FieldRegistrar class using this trait.

    // The field instance associated with this item.
    public ?Field $field = null;

    /**
     * Adds a field to the item.
     *
     * @param  string|null  $type     The type of field to add (e.g. 'text', 'checkbox', etc.).
     * @param  mixed|null   $config   Optional configuration for the field.
     * @param  Closure|null $callback Optional callback for rendering the field.
     * @param  array        $args     Additional arguments for the field.
     * 
     * @return Field The created Field instance.
     */
    public function field(?string $type = null, mixed $config = null, ?Closure $callback = null, array $args = []): Field {
        if ($this->field !== null) {
            throw new \Exception("Item '{$this->name}' already has a field.");
        }

        if (!$this->compatibleWithField()) {
            throw new \Exception("Item '{$this->name}' is not compatible with fields.");
        }

        $type = $type ?? $this->getDefaultFieldType();

        if (!method_exists($this->fieldClass, $type)) {
            throw new \Exception("Invalid field type '{$type}'.");
        }

        // Allow config as closure shorthand
        if ($config instanceof Closure) {
            $callback = $config;
            $config   = null;
        }

        $field = new $this->fieldClass(
            source:    $this->source,
            registrar: $this,
            callback:  $callback
        );

        $field->type($type, $args);

        // 👇 Delegate ALL config handling to Field
        if ($config !== null) {
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

            $type     = $item->getType();
            $itemType = $item->getItemType();

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

                $fieldType = $map[$resolvedType]
                    ?? ($resolvedType === 'array.object' ? 'repeater' : null);

                $item->field($fieldType);

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
     * Checks if the item is compatible with having a field added to it.
     *
     * @return boolean
     */
    protected function compatibleWithField(): bool {
        $compatibleTypes = ['string', 'boolean', 'integer', 'number', 'array'];

        return in_array($this->getType(), $compatibleTypes);
    }

    /**
     * Returns a default field type using this item's value type.
     *
     * @return string
     */
    public function getDefaultFieldType(): string {
        return match ($this->getType() ?? 'string') {
            'string'            => 'text',
            'boolean'           => 'checkbox',
            'integer', 'number' => 'number',
            'array'             => 'repeater',
            default => 'text',
        };
    }

    /**
     * Retrieves an array of field names defined in the current object definition.
     *
     * @return array An array of field names.
     */
    public function getFieldNames(): array {
        return array_map(
            fn($item) => $item->name,
            $this->subItems
        );
    }

    /**
     * Retrieves an array of input names for all fields defined in the current object definition.
     *
     * @return array An array of input names.
     */
    public function getInputNames(): array {
        $names = [];

        $this->walkFields(function(Field $field) use (&$names) {
            $names[] = $field->getName();
        });

        return $names;
    }

    /**
     * Returns the option name (handle) for the item.
     *
     * @return string
     */
    public function getID(): string {
        return $this->name ?? '';
    }

    /**
     * Returns the name for the item.
     *
     * @return string
     */
    public function getName(): string {
        return $this->getID();
    }

    /**
     * Returns the label for the item if available or generates a label using $this->name.
     *
     * @return string
     */
    public function getLabel(): string {
        $label = $this->args['label'] ?? '';
        
        if ($label !== '') {
            return $label;
        }

        return Str::title(Str::replace('_', ' ', $this->name));
    }

    /**
     * Returns the description for the item if available.
     * Otherwise, returns an empty string.
     * 
     * @return string
     */
    public function getDescription(): string {
        return $this->args['description'] ?? '';
    }
}