<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Field;

use MM\Meros\Facades\Fields as FieldsRegister;

trait CanAttachFields {
    /**
     * Field instances that belong to this field group.
     *
     * @var array<string|Field>
     */
    public array $fields = [];

    protected string $fieldWrapper = 'meros::fields.wrappers.site-field';

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Adds a field instance to the item's fields array. 
     *
     * @param Field|array<Field> $field A single Field instance or an array of Field instances to add to the group.
     *
     * @return self
     */
    public function attach(Field|array $field): self {
        if (is_array($field)) {
            $this->fields = array_merge($this->fields, $field);

            $this->walkFields(function(Field $field) {
                $field->parent($this);
                $field->wrapper($this->fieldWrapper);
            });

        } else {
            $field->wrapper($this->fieldWrapper);
            $field->parent($this);
            $this->fields[] = $field;
        }

        return $this;
    }

    /**
     * Creates a new field instance, adds it to the item's fields array, and returns it for chaining.
     *
     * @param string             $fieldIdOrClass The class name of the field to instantiate.
     * @param Closure|array|null $callback An optional callback function or array of properties to apply to the field instance after creation.
     * @param array              $props Additional configuration options for the field instance.
     *
     * @return Field The created field instance.
     */
    public function field(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        $params = func_num_args();

        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }
    
        $field = FieldsRegister::checkout($this->provider)->makeFrom($fieldIdOrClass, $props);

        if ($callback instanceof Closure) {
            $callback($field);
        }

        $this->attach($field);
        return $field;
    }

    /***************************
     * Alias methods for field()
     ***************************/

    /**
     * Alias for the field() method to create and attach a sub-field to the group.
     *
     * @param string                  $fieldIdOrClass
     * @param Closure|array|null|null $callback
     * @param array                   $props
     *
     * @return Field
     */
    public function sub(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        if (is_array($callback)) {
            $props = $callback;
            return $this->field($fieldIdOrClass, $props);
        }    
        
        return $this->field($fieldIdOrClass, $callback, $props);
    }

    /**
     * Alias for the field() method to create and attach a sub-field to the group.
     *
     * @param string                  $fieldIdOrClass
     * @param Closure|array|null|null $callback
     * @param array                   $props
     *
     * @return Field
     */
    public function subField(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        if (is_array($callback)) {
            $props = $callback;
            return $this->field($fieldIdOrClass, $props);
        }

        return $this->field($fieldIdOrClass, $callback, $props);
    }

    /**
     * Alias for the field() method to create and attach a sub-field to the group.
     *
     * @param string                  $fieldIdOrClass
     * @param Closure|array|null|null $callback
     * @param array                   $props
     *
     * @return Field
     */
    public function addField(string $fieldIdOrClass, Closure|array|null $callback = null, array $props = []): Field {
        if (is_array($callback)) {
            $props = $callback;
            return $this->field($fieldIdOrClass, $props);
        }

        return $this->field($fieldIdOrClass, $callback, $props);
    }

    /**
     * Sets the Blade view to use as a wrapper when rendering fields in this group.
     *
     * @param string $view The view name (e.g. 'meros::fields.wrappers.default').
     *
     * @return self
     */
    public function fieldWrapper(string $view): self {
        $this->fieldWrapper = $view;

        $this->walkFields(function(Field $field) use ($view) {
            $field->wrapper($view);
        });

        return $this;
    }
        
    /***************************
     * Helpers
     ***************************/

    /**
     * Instantiates any fields in the group that are currently represented as class name strings.
     *
     * @return void
     */
    protected function instantiateFields(): void {
        foreach ($this->fields as $index => $field) {
            if (is_string($field)) {
                // Instantiate the field if it's a string (class name)
                $this->fields[$index] = FieldsRegister::checkout($this->provider)->makeFrom($field);
            }
        }
    }

    /**
     * Retrieves the collection fields in the group.
     *
     * @return Collection
     */
    public function getFields(): Collection {
        return collect($this->fields);
    }

    /**
     * Walks through each field in the group and applies a callback function.
     *
     * @param Closure $callback A callback function that accepts a Field instance as its parameter.
     *
     * @return void
     */
    protected function walkFields(Closure $callback): void {
        foreach ($this->fields as $field) {
            if ($field instanceof Field) {
                $callback($field);
            }
        }
    }

    /**
     * Retrieves the names of all sub-items defined for the repeater field.
     *
     * @return array
     */
    public function getFieldNames(): array {
        return collect($this->fields)
            ->map(fn($field) => $field->getName())
            ->toArray();
    }

    /**
     * Retrieves the labels of all sub-items defined for the repeater field.
     *
     * @return array
     */
    public function getFieldLabels(): array {
        return collect($this->fields)
            ->map(fn($field) => $field->getLabel())
            ->toArray();
    }
}