<?php

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Storable;
use MM\Meros\Contracts\Features\StorableItem;

use MM\Meros\Contracts\Features\Concerns\IsHookable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\MakesItems;
use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;

use MM\Meros\Support\Sanitizer;

abstract class DataContainer extends Feature implements Storable {
    /**
     * The prefix to be used with the container's name.
     *
     * @var string
     */
    protected string $prefix = '';

    /**
     * The unique name of the container.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The default value of the container.
     *
     * @var array
     */
    protected array $default = [];

    /**
     * Whether the container should be exposed in the REST API.
     *
     * @var bool
     */
    protected bool $showInRest = false;

    /**
     * The cached value of the container.
     *
     * @var array
     */
    protected array $cachedValue = [];

    /**
     * The class name of the DataItem implementing class associated with this container.
     *
     * @var string
     */
    protected string $itemClass = '';

    /**
     * An array of DataItem instances associated with this container.
     *
     * @var array<StorableItem>
     */
    protected array $items = [];

    /**
     * The name of the hook to be used when the container is updated.
     *
     * @var string
     */
    protected string $updatedHook = '';

    /**
     * The callback to be executed when the container's value is updated.
     *
     * @var Closure|null
     */
    protected ?Closure $onUpdateCallback = null;

    /**
     * An optional callback to be called when an item is added to the container.
     *
     * @var Closure|null
     */
    private ?Closure $onAddCallback = null;

    use IsHookable, IsRegistrable, IsMakeable, InstantiatesItems, MakesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        $this->identifier('name', 'snake');
    }

    protected function whenConfigured(): void {
        if (empty($this->name)) {
            throw new \LogicException("The container name must be set before the whenConfigured() method is called in " . static::class);
        }

        if (empty($this->itemClass)) {
            throw new \LogicException("The item class must be set in the configure() method of " . static::class);
        }

        $this->hook();
    }

    /**
     * Sets the class name of the DataItem implementing class associated with this container.
     * Should be called by subclasses in the configure() method to specify the item class.
     *
     * @param string $itemClass
     *
     * @return static
     */
    final protected function setItemClass(string $itemClass): static {
        $this->itemClass = $itemClass;
        return $this;
    }

    /**
     * Sets the hook to be fired when the container is updated. 
     * Should be called by subclasses in the configure() method.
     *
     * @param string $hook
     *
     * @return static
     */
    final protected function updatedHook(string $hook): static {
        $this->updatedHook = $hook;
        return $this;
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    /**
     * The default callback function to be executed when the feature is hooked.
     *
     * @return void
     */
    final public function defaultHookCallback(): void {
        $this->registerContainer();
        $this->afterRegister();
    }

    abstract public function registerContainer(): void;
    abstract public function unregisterContainer(): void;

    private function afterRegister(): void {
        if (!empty($this->updatedHook)) {
            add_filter($this->updatedHook, function (mixed $value, mixed $oldValue, string $optionName) {
                $this->__whenUpdated($value, $oldValue, $optionName);
                return $value;
            }, 10, 3);
        }
    }

    /**
     * Fires when the container is updated. Can be overridden in subclasses to perform actions when the settings container is updated.
     *
     * @param mixed  $value
     * @param mixed  $oldValue
     * @param string $optionName
     *
     * @return void
     */
    private function __whenUpdated(mixed $value, mixed $oldValue, string $optionName): void {
        // This method can be overridden in subclasses to perform actions when the settings container is updated.
        $this->getItems(true)->each(function (StorableItem $item) use ($value, $oldValue, $optionName) {
            if (method_exists($item, 'whenUpdated')) {
                $name       = $item->getName();
                $optionName = $optionName . '[' . $name . ']';
                $value      = $value[$name] ?? null;
                $oldValue   = $oldValue[$name] ?? null;

                $item->whenUpdated($value, $oldValue, $name, $optionName);
            }
        });

        $this->whenUpdated($value, $oldValue, $optionName);
    }

    /**
     * Fires when the container is updated. Can be overridden in subclasses to perform actions when the settings container is updated.
     *
     * @param mixed  $value
     * @param mixed  $oldValue
     * @param string $optionName
     *
     * @return void
     */
    protected function whenUpdated(mixed $value, mixed $oldValue, string $optionName): void {
        if (is_callable($this->onUpdateCallback)) {
            call_user_func($this->onUpdateCallback, $value, $oldValue, $optionName);
        }
    }

    // =========================================================================
    // DataItem Management
    // =========================================================================

    /**
     * Registers a new DataItem class with the container's item register.
     *
     * @param string $itemClass The class name of the item to register.
     * @param string $alias     An optional alias for the item class.
     *
     * @return void
     */
    final public function register(string $itemClass, string $alias = ''): void {
        $register = $this->resolveRegistrarRegister($this->itemClass);
        $register->checkout($this->getProvider())->register($itemClass, $alias);
    }

    /**
     * Creates a new StorableItem instance from the specified class or alias and adds it to the container.
     *
     * @param string        $itemClassOrAlias The class name or alias of the StorableItem to create.
     * @param Closure|array $callbackOrProps  An optional callback to modify the item instance after creation, or an array of properties to pass to the item's constructor.
     * @param array         $props            An array of properties to pass to the item's constructor.
     *
     * @return StorableItem The newly created StorableItem instance.
     */
    final public function makeFrom(string $itemClassOrAlias, Closure|array $callbackOrProps = [], array $props = []): StorableItem {
        $item = $this->makeItemFrom($itemClassOrAlias, $this->itemClass, $callbackOrProps, $props);
        $this->items[] = $item;
        $this->afterAdd($item);
        return $item;
    }

    /**
     * Adds a new StorableItem to the container and returns the item instance.
     *
     * @param string|Closure|array $typeCallbackOrProps The type of the StorableItem to add, a closure to configure the item, or an array of properties to pass to the item's constructor.
     * @param Closure|array        $callbackOrProps     An optional callback to modify the item instance after creation, or an array of properties to pass to the item's constructor.
     * @param array                $props               An array of properties to pass to the item's constructor.
     *
     * @return StorableItem The newly created StorableItem instance.
     * @throws \LogicException if the container name is not set before adding items.
     */
    final public function add(string|Closure|array $typeCallbackOrProps, Closure|array $callbackOrProps = [], array $props = []): StorableItem {
        if (empty($this->name)) {
            throw new \LogicException("The container name must be set before adding items.");
        }

        if (is_string($typeCallbackOrProps)) {
            $dataType = $typeCallbackOrProps;
            $props['data_type'] = $dataType;

            if ($dataType === 'object') {
                $props['data_type'] = 'array';
                $props['nested_data_type'] = 'object';
            }
            
        } else {
            $callbackOrProps = $typeCallbackOrProps;
        }

        $item = $this->makeDataItem($callbackOrProps, array_merge($props, ['container' => $this]));

        $this->items[] = $item;

        if (is_callable($this->onAddCallback)) {
            call_user_func($this->onAddCallback, $item, $this);
        }

        $this->afterAdd($item);
        return $item;
    }

    /**
     * For internal use only. Pushes an instantiated StorableItem to the container's items array.
     *
     * @param StorableItem $item
     *
     * @return static
     */
    final public function __pushItem(StorableItem $item): static {
        $this->items[] = $item;
        $this->afterAdd($item);
        return $this;
    }

    /**
     * Instantiates a new StorableItem from the specified class and assigns it to the container.
     *
     * @param Closure|array $callbackOrProps An optional callback to modify the item instance after creation, or an array of properties to pass to the item's constructor.
     * @param array         $props           An array of properties to pass to the item's constructor.
     *
     * @return StorableItem The newly created StorableItem instance.
     */
    private function makeDataItem(Closure|array $callbackOrProps = [], array $props = []): StorableItem {
        $item = $this->makeItem($this->itemClass, $callbackOrProps, $props);

        if (!$item instanceof StorableItem) {
            throw new \InvalidArgumentException("The item class must implement the StorableItem interface.");
        }

        return $item;
    }

    /**
     * Runs after a new item is added to the $items array. 
     * 
     * Can be overriden by concrete classes for any post-processing of the item.
     *
     * @param StorableItem $item
     *
     * @return void
     */
    protected function afterAdd(StorableItem $item): void {
        // Can be overriden by concrete classes for processing a StorableItem instance after being added to the container.
    }

    /**
     * Returns the array of StorableItem instances associated with this container.
     *
     * @param bool $collect If true, returns a Collection instead of an array.
     *
     * @return array|Collection The array or Collection of StorableItem instances.
     */
    final public function getItems(bool $collect = false): array|Collection {
        return $collect ? collect($this->items) : $this->items;
    }

    /**
     * Checks if the container has an item with the specified name.
     *
     * @param string $name
     *
     * @return boolean
     */
    final public function hasItem(string $name): bool {
        return $this->getItems(true)->contains(fn(StorableItem $item) => $item->getName() === $name);
    }

    // =========================================================================
    // Sanitization and Value Processing
    // =========================================================================

    /**
     * Returns the default value of the container, which is an associative array of item names and their default values.
     *
     * @return array The default value of the container.
     */
    final public function getDefault(): array {
        $this->getItems(true)->each(function (StorableItem $item) {
            $this->default[$item->getName()] = $item->getDefault();
        });

        return $this->default;
    }

    /**
     * Returns the default value of a specific item in the container by its key.
     *
     * @param string $key The key of the item to retrieve the default value for.
     *
     * @return mixed The default value of the specified item, or null if not found.
     */
    final public function getItemDefault(string $key): mixed {
        $item = $this->getItems(true)->first(fn($item) => $item->getName() === $key);
        
        if ($item instanceof StorableItem) {
            return $item->getDefault();
        }

        return null;
    }

    /**
     * Returns the current value of the container, optionally refreshing the cached value.
     *
     * @param bool $refresh If true, refreshes the cached value from the source.
     *
     * @return array The current value of the container.
     */
    public function getValue(bool $refresh = false): array {
        if ($refresh || empty($this->cachedValue)) {
            $rawValue = $this->getRawValue();
            $this->cachedValue = $this->processRawValue($rawValue);
        }

        return $this->cachedValue;
    }

    /**
     * Returns the value of a specific item in the container by its key, optionally refreshing the cached value.
     *
     * @param string $key     The key of the item to retrieve.
     * @param bool   $refresh If true, refreshes the cached value from the source.
     *
     * @return mixed The value of the specified item, or null if not found.
     */
    public function getItemValue(string $key, bool $refresh = false): mixed {
        $value = $this->getValue($refresh);
        return $value[$key] ?? $this->getItemDefault($key);
    }

    /**
     * Returns the raw value of the container from the underlying storage, without any processing or caching.
     * Must be implemented by subclasses to define how the raw value is retrieved.
     *
     * @return array
     */
    abstract protected function getRawValue(): array;

    /**
     * Processes the raw value of the container and returns the processed value.
     * 
     * This method should be overridden by subclasses to define how the raw value is 
     * transformed into the final value after retrieval from storage.
     *
     * @param array $rawValue The raw value of the container.
     *
     * @return array The processed value of the container.
     */
    protected function processRawValue(array $rawValue): array {
        return $rawValue;
    }

    /**
     * Sets the value of the container and updates the underlying storage.
     * Must be implemented by subclasses to define how the value is stored.
     *
     * @param array $value The value to set for the container.
     *
     * @return void
     */
    abstract protected function setValue(array $value): void;

    /**
     * Sets the value of a specific item in the container by its key and updates the container's value.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function setItemValue(string $key, mixed $value): void {
        $oldValue = $this->getValue(true);
        $newValue = $this->sanitizeValue(array_merge($oldValue, [$key => $value]));

        $this->setValue($newValue);
        $this->cachedValue = $newValue;
        $this->__whenUpdated($newValue, $oldValue, $this->name);
    }

    /**
     * Sanitizes the given value based on the container's schema and returns the sanitized value.
     *
     * @param array $value The value to be sanitized.
     *
     * @return array The sanitized value.
     */
    public function sanitizeValue($value): array {
        return Sanitizer::sanitize($value, $this->getSchema());
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the unique name of the container and returns the container instance.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        $name = $this->setIdentifier($name);
        $this->name = $this->prefix !== '' ? $this->prefix . '_' . $name : $name;

        $this->afterNameSet();
        return $this;
    }

    /**
     * Called after the name of the container has been set. 
     * 
     * This method can be overridden by subclasses to perform additional 
     * actions after the name is set.
     *
     * @return void
     */
    protected function afterNameSet(): void {
        // This method can be overridden by subclasses to perform actions after the name is set.
    }

    /**
     * Sets whether the container should be exposed in the REST API and returns the container instance.
     *
     * @param bool $show
     *
     * @return static
     */
    final public function showInRest(bool $show = true): static {
        $this->showInRest = $show;
        return $this;
    }

    /**
     * Sets a callback which is called when an item is added to the container.
     *
     * @param Closure $callback
     *
     * @return static
     */
    final public function onAdd(Closure $callback): static {
        $this->onAddCallback = $callback;
        return $this;
    }

     /**
     * Sets a callback to be executed when the container's value is updated and returns the container instance.
     *
     * @param Closure $callback The callback to execute on update. It should accept parameters: value, oldValue, and optionName.
     *
     * @return static
     */
    final public function onUpdate(Closure $callback): static {
        $this->onUpdateCallback = $callback;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the name of the container, optionally with or without the prefix.
     *
     * @param bool   $withPrefix Whether to include the prefix in the name (default: false).
     * @param string $format The format of the name to return. Can be 'default', 'snake', or 'slug'. Defaults to 'default'.
     *
     * @return string
     */
    final public function getName(bool $withPrefix = false, string $format = 'default'): string {
        $name = $this->getIdentifier($format);
        
        if ($withPrefix && $this->prefix !== '') {
            return $name;
        }

        if (!$withPrefix && $this->prefix !== '') {
            return Str::replace($this->prefix . '_', '', $name);
        }

        return $name;
    }

    /**
     * Returns the schema of the container, which defines the structure and properties of the data it holds.
     *
     * @return array
     */
    final public function getSchema(): array {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        if (!empty($this->label)) {
            $schema['title'] = $this->getLabel();
        }

        if (!empty($this->description)) {
            $schema['description'] = $this->getDescription();
        }

        $default = $this->getDefault();

        if (!empty($default)) {
            $schema['default'] = $default;
        }

        $this->getItems(true)->each(function (StorableItem $item) use (&$schema) {
            $itemSchema = match ($item->getDataType()) {
                'array.object' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'array',
                    ],
                ],
                'array.scalar' => [
                    'type' => 'array',
                    'items' => [
                        'type' => $item->getNestedDataType()
                    ],
                ],
                default => [
                    'type' => $item->getDataType(),
                ],
            };

            if (method_exists($item, 'getLabel')) {
                $label = $item->getLabel();

                if (!empty($label)) {
                    $itemSchema['title'] = $label;
                }
            }

            if (method_exists($item, 'getDescription')) {
                $description = $item->getDescription();

                if (!empty($description)) {
                    $itemSchema['description'] = $description;
                }
            }

            $default = $item->getDefault();

            if ($default !== null) {
                $itemSchema['default'] = $default;
            }

            $schema['properties'][$item->getName()] = $itemSchema;
        });

        return ['schema' => $schema];
    }
}