<?php

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Storable;
use MM\Meros\Contracts\Features\StorableItem;

use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

abstract class DataItem extends Feature implements StorableItem {
    /**
     * The associated DataContainer instance for this DataItem.
     *
     * @var Storable|null
     */
    protected ?Storable $container = null;

    /**
     * The Field instance associated with this DataItem.
     *
     * @var Field|null
     */
    protected ?Field $field = null;
    
    /**
     * The unique name of the data item.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The human-readable label of the data item.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The description of the data item.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The default value of the data item.
     *
     * @var mixed
     */
    protected mixed $default = null;

    /**
     * The arguments associated with the data item.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * The data type of the data item.
     *
     * @var string
     */
    protected string $dataType = '';

    /**
     * The nested data type for array data items.
     *
     * @var string
     */
    protected string $nestedDataType = '';

    /**
     * The supported data types for the data item.
     *
     * @var array
     */
    private array $dataTypes = [
        'string',
        'integer',
        'number',
        'boolean',
        'array',
        'object'
    ];

    use IsRegistrable, IsMakeable, InstantiatesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        if (isset($this->passedProps['container']) && $this->passedProps['container'] instanceof Storable) {
            $this->container($this->passedProps['container']);
        }

        if (isset($this->passedProps['data_type'])) {
            $this->setDataType($this->passedProps['data_type']);
        }
    }

    protected function whenConfigured(): void {
        if ($this->creationMethod === 'made_from_class') {
            if (empty($this->dataType)) {
                throw new \LogicException("DataItem '{$this->name}' must have a data type set.");
            }

            $this->container($this->resolveContainer());
        }

        if ($this->container === null) {
            throw new \LogicException("DataItem '{$this->name}' must be associated with a Storable.");
        }
    }

    // =========================================================================
    // Container Association
    // =========================================================================

    /**
     * Should resolve the associated DataContainer for this DataItem.
     * 
     * This method should be implemented by subclasses to provide the 
     * appropriate Storable instance for the StorableItem in cases
     * where the item has not been explicitly associated with a container via
     * the container() method.
     *
     * @return Storable|null The associated Storable instance or null.
     */
    abstract protected function resolveContainer(): ?Storable;

    /**
     * Associates this DataItem with a DataContainer and returns the DataItem instance.
     *
     * @param Storable $container
     *
     * @return static
     */
    public function container(Storable $container): static {
        $this->container = $container;
        $this->whenContainerSet();
        return $this;
    }

    /**
     * This method is called when a container is set for the DataItem. 
     * 
     * It can be overridden in subclasses to perform additional actions 
     * when a container is associated with the DataItem.
     *
     * @return void
     */
    protected function whenContainerSet(): void {
        // This method can be overridden in subclasses to perform actions when a container is set.
    }

    /**
     * Ensures that this DataItem is associated with a DataContainer.
     *
     * @throws \BadMethodCallException If the DataItem is not associated with a Storable.
     */
    private function ensureContainer(): void {
        if (!isset($this->container)) {
            throw new \BadMethodCallException("DataItem '{$this->name}' must be associated with a Storable.");
        }
    }

    /**
     * Returns the associated DataContainer instance if it exists, or null otherwise.
     *
     * @return Storable|null The associated Storable instance or null.
     */
    final public function getContainer(): ?Storable {
        if (!isset($this->container)) {
            return null;
        }

        return $this->container;
    }

    final public function __call(string $method, array $args) {
        if (method_exists($this, $method)) {
            if (!in_array($method, ['__construct', 'container', 'ensureContainer'])) {
                $this->ensureContainer();
            }

            return $this->$method(...$args);
        }

        throw new \BadMethodCallException("Method '{$method}' does not exist on " . static::class);
    }

    // =========================================================================
    // Field Association
    // =========================================================================

    /**
     * Associates a Field instance with this DataItem and returns it.
     *
     * @param string|null   $type The type of the field to associate. Can be a string representing the field type or null to infer from data type.
     * @param Closure|array $callbackOrProps A closure for configuring the field or an array of properties for the field.
     *
     * @return Field The associated Field instance.
     *
     * @throws \BadMethodCallException If the DataItem is not compatible with fields or if the name is not set before associating a field.
     * @throws \InvalidArgumentException If the field type is not compatible with the data type of the DataItem.
     * @throws \LogicException If the created field is not an instance of Field.
     */
    public function field(?string $type = null, Closure|array $callbackOrProps = []): Field {
        $this->beforeFieldSet();

        if ($this->name === 'placeholder_id') {
            throw new \BadMethodCallException("DataItem '{$this->name}' must have a name set before associating a field.");
        }

        $dataType   = $this->getDataType();
        $compatible = $dataType !== 'object';

        if (!$compatible) {
            throw new \BadMethodCallException("DataItem '{$this->name}' of type '{$dataType}' is not compatible with fields.");
        }

        $fieldType = is_string($type) && !empty($type) ? $type : $this->inferFieldType($dataType);
        $field     = $this->makeItemFrom($fieldType, Field::class, $callbackOrProps);

        if (!($field instanceof Field)) {
            throw new \LogicException("The created field must be an instance of Field.");
        }

        $this->field = $field;

        if (!$this->field->isCompatibleWithDataType($dataType)) {
            throw new \InvalidArgumentException("Field of type '{$fieldType}' is not compatible with data type '{$dataType}'.");
        }
        
        // Sync field attributes with this DataItem
        $containerName = $this->container->getName(true);
        $this->field->name($containerName . '[' . $this->name . ']');
        $this->field->id($containerName . '-' . Str::replace('_', '-', $this->name));
        $this->field->label($this->label);
        $this->field->description($this->description);
        $this->field->default($this->default);
    
        $this->whenFieldSet($this->field);
        return $this->field;
    }

    /**
     * Infers the field type based on the data type of the item.
     *
     * @return string The inferred field type.
     */
    final protected function inferFieldType(string $dataType): string {
        return match ($dataType) {
            'array.object'      => 'repeater',
            'array.scalar'      => 'multi_select',
            'string'            => 'text',
            'boolean'           => 'checkbox',
            'integer', 'number' => 'number',
            default             => 'text'
        };
    }

    /**
     * This method is called before a field is set for the DataItem. 
     * 
     * It can be overridden in subclasses to perform additional actions 
     * before a field is associated with the DataItem.
     *
     * @return void
     */
    protected function beforeFieldSet(): void {
        // This method can be overridden in subclasses to perform actions before a field is set.
    }

    /**
     * This method is called when a field is set for the DataItem. 
     * 
     * It can be overridden in subclasses to perform additional actions 
     * when a field is associated with the DataItem.
     * 
     * @param Field $field The Field instance that has been set for the DataItem.
     *
     * @return void
     */
    protected function whenFieldSet(Field $field): void {
        // This method can be overridden in subclasses to perform actions when a field is set.
    }

    /**
     * Returns the associated Field instance if it exists, or null otherwise.
     *
     * @return Field|null The associated Field instance or null.
     */
    final public function getField(): ?Field {
        if ($this->field instanceof Field) {
            return $this->field;
        }

        return null;
    }

    /**
     * Checks if a Field instance is associated with this DataItem.
     *
     * @return bool True if a Field instance is associated, false otherwise.
     */
    final public function hasField(): bool {
        return $this->field instanceof Field;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $identifier): static {
        return $this->name($identifier);
    }

    /**
     * Sets the unique name of the data item and returns the data item instance.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        $this->name = Str::snake($name);

        if (empty($this->label)) {
            $this->label = Str::title(str_replace('_', ' ', $name));
        }

        return $this;
    }

    /**
     * Sets the human-readable label of the data item and returns the data item instance.
     *
     * @param string $label
     *
     * @return static
     */
    final public function label(string $label): static {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the description of the data item and returns the data item instance.
     *
     * @param string $description
     *
     * @return static
     */
    final public function description(string $description): static {
        $this->description = $description;
        return $this;
    }

    /**
     * Sets the default value of the data item and returns the data item instance.
     *
     * @param mixed $value The default value to set for the data item.
     *
     * @return static
     *
     * @throws \InvalidArgumentException If the provided default value does not match the data type of the data item.
     */
    final public function default(mixed $value): static {
        $type = $this->dataType;

        if (!empty($type)) {
            $valid = match ($type) {
                'string'  => is_string($value),
                'integer' => is_int($value),
                'number'  => is_numeric($value),
                'boolean' => is_bool($value),
                'array'   => is_array($value),
                'object'  => is_array($value) || is_object($value),
                default   => true,
            };

            if (!$valid) {
                throw new \InvalidArgumentException("Default value for data type '{$type}' is not valid.");
            }
        }

        $this->default = $value;
        return $this;
    }

    // =========================================================================
    // Data Type Setters
    // =========================================================================

    /**
     * Sets the data type of the data item to 'string' and returns the data item instance.
     *
     * @param string $name Optional name to set for the data item.
     *
     * @return static
     */
    final public function string(string $name = ''): static {
        return $this->setDataType('string', $name);
    }

    /**
     * Sets the data type of the data item to 'integer' and returns the data item instance.
     *
     * @param string $name Optional name to set for the data item.
     *
     * @return static
     */
    final public function integer(string $name = ''): static {
        return $this->setDataType('integer', $name);
    }

    /**
     * Sets the data type of the data item to 'number' and returns the data item instance.
     *
     * @param string $name Optional name to set for the data item.
     *
     * @return static
     */
    final public function number(string $name = ''): static {
        return $this->setDataType('number', $name);
    }

    /**
     * Sets the data type of the data item to 'boolean' and returns the data item instance.
     *
     * @param string $name Optional name to set for the data item.
     *
     * @return static
     */
    final public function boolean(string $name = ''): static {
        return $this->setDataType('boolean', $name);
    }

    /**
     * Sets the data type of the data item to 'array' and returns the data item instance.
     *
     * @param string $name Optional name to set for the data item.
     *
     * @return static
     */
    final public function object(string $name = ''): static {
        return $this->setDataType('object', $name);
    }

    /**
     * Sets the data type of the data item to 'array' and returns the data item instance.
     *
     * @param string $name Optional name to set for the data item.
     *
     * @return static
     */
    final public function array(string $name = ''): static {
        $this->setDataType('array', $name);
        
        if ($this->nestedDataType === '') {
            $this->nestedDataType = 'string'; // Default to string if not set
        }

        return $this;
    }

    /**
     * Sets the nested data type for an array data item and returns the data item instance.
     *
     * @param string $nestedType The nested data type to set (e.g., 'string', 'integer', 'object').
     *
     * @return static
     *
     * @throws \BadMethodCallException If the data item is not of type 'array'.
     * @throws \InvalidArgumentException If the provided nested data type is not supported.
     */
    final public function of(string $nestedType): static {
        if (empty($this->dataType) || $this->dataType !== 'array') {
            throw new \BadMethodCallException("The 'of' method can only be called on a DataItem of type 'array'.");
        }

        $nestedType = Str::singular($nestedType); // Normalise to singular form incase user passes plural form

        // Prevent nested arrays and unsupported types
        if ($nestedType === 'array' || !in_array($nestedType, $this->dataTypes)) {
            throw new \InvalidArgumentException("Nested data type '{$nestedType}' is not supported. Supported data types are: " . implode(', ', $this->dataTypes));
        }

        $this->nestedDataType = $nestedType;
        return $this;
    }

    /**
     * Sets the data type of the data item and optionally its name, ensuring that the provided data type is supported.
     *
     * @param string $dataType The data type to set for the data item.
     * @param string $name     Optional name to set for the data item.
     *
     * @return static
     *
     * @throws \LogicException If the data type has already been set and differs from the provided data type.
     * @throws \InvalidArgumentException If the provided data type is not supported.
     */
    private function setDataType(string $dataType, string $name = ''): static {
        if (!empty($this->dataType) && $this->dataType !== $dataType) {
            throw new \LogicException("DataItem '{$this->name}' already has a data type of '{$this->dataType}'. Cannot change to '{$dataType}'.");
        }

        if (!in_array($dataType, $this->dataTypes)) {
            throw new \InvalidArgumentException("Data type '{$dataType}' is not supported. Supported data types are: " . implode(', ', $this->dataTypes));
        }

        $this->dataType = $dataType;

        if (!empty($name)) {
            $this->name($name);
        }

        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the data type of the data item, including nested data type for arrays.
     *
     * @return string The data type of the data item.
     */
    final public function getDataType(): string {
        if ($this->dataType === 'array') {
            $nestedDataType = $this->nestedDataType === 'object' ? 'object' : 'scalar';
            return "{$this->dataType}.{$nestedDataType}";
        }

        return $this->dataType;
    }

    /**
     * Returns the nested data type for array data items.
     *
     * @return string The nested data type for array data items.
     */
    final public function getNestedDataType(): string {
        return $this->nestedDataType;
    }

    /**
     * Returns the unique name of the data item.
     *
     * @return string
     */
    final public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the human-readable label of the data item.
     *
     * @return string
     */
    final public function getLabel(): string {
        return $this->label;
    }

    /**
     * Returns the description of the data item.
     *
     * @return string
     */
    final public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns the default value of the data item.
     *
     * @return mixed
     */
    final public function getDefault(): mixed {
        return $this->default;
    }

    /**
     * Returns the value of the data item, optionally refreshing it from the container.
     *
     * @param boolean $refresh
     *
     * @return mixed
     */
    final public function getValue(bool $refresh = false): mixed {
        return $this->container->getItemValue($this->name, $refresh);
    }

    /**
     * Returns the args array of the data item.
     * 
     * @return array The args array of the data item.
     */
    final public function getArgs(): array {
        return $this->args;
    }

    final public function getIdentifier(): string {
        return $this->name;
    }
}