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
     * The human-readable label of the container.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The description of the container.
     *
     * @var string
     */
    protected string $description = '';

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
     * An array of DataItem instances or classes associated with this container.
     *
     * @var array<StorableItem|string>
     */
    protected array $items = [];

    use IsHookable, IsRegistrable, IsMakeable, InstantiatesItems, MakesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function whenConfigured(): void {
        if (empty($this->itemClass)) {
            throw new \LogicException("The item class must be set in the configure() method of " . static::class);
        }

        if (!empty($this->items)) {
            $this->instantiate('items', $this->itemClass);
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
    }

    abstract public function registerContainer(): void;
    abstract public function unregisterContainer(): void;

    // =========================================================================
    // DataItem Management
    // =========================================================================

    /**
     * Registers a new DataItem class with the container's item register.
     *
     * @param string $itemClass
     * @param string $alias
     *
     * @return void
     */
    final public function register(string $itemClass, string $alias = ''): void {
        $register = $this->resolveRegistrarRegister($this->itemClass);
        $register->register($itemClass, $alias);
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
    final public function make(string $itemClassOrAlias, Closure|array $callbackOrProps = [], array $props = []): StorableItem {
        $item = $this->makeItemFrom($itemClassOrAlias, $this->itemClass, $callbackOrProps, $props);
        $this->items[] = $item;
        $this->afterAdd($item);
        return $item;
    }

    /**
     * Adds a new DataItem to the container and returns the item instance.
     *
     * @param string|Closure|array $typeCallbackOrProps The type of the data item to add, a closure to configure the item, or an array of properties to pass to the item's constructor.
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
        } else {
            $callbackOrProps = $typeCallbackOrProps;
        }

        $item = $this->makeDataItem($callbackOrProps, array_merge($props, ['container' => $this]));

        $this->items[] = $item;
        $this->afterAdd($item);
        return $item;
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
     * Sanitizes the given value based on the container's schema and returns the sanitized value.
     *
     * @param array $value The value to be sanitized.
     *
     * @return array The sanitized value.
     */
    public function sanitizeValue(array $value): array {
        return Sanitizer::sanitize($value, $this->getSchema());
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $identifier): static {
        return $this->name($identifier);
    }

    /**
     * Sets the unique name of the container and returns the container instance.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        $snakeName  = Str::snake($name);
        $this->name = $this->prefix !== '' ? $this->prefix . '_' . $snakeName : $snakeName;

        if (empty($this->label)) {
            $this->label = Str::title(str_replace('_', ' ', $name));
        }

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
     * Sets the human-readable label of the container and returns the container instance.
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
     * Sets the description of the container and returns the container instance.
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

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the name of the container, optionally with or without the prefix.
     *
     * @param bool $withPrefix Whether to include the prefix in the name (default: false).
     *
     * @return string
     */
    final public function getName(bool $withPrefix = false): string {
        if ($withPrefix && $this->prefix !== '') {
            return $this->name;
        }

        if (!$withPrefix && $this->prefix !== '') {
            return Str::replace($this->prefix . '_', '', $this->name);
        }

        return $this->name;
    }

    /**
     * Returns the schema of the container, which defines the structure and properties of the data it holds.
     *
     * @return array
     */
    final protected function getSchema(): array {
        $schema = [
            'type' => 'object',
            'properties' => [],
        ];

        if (!empty($this->label)) {
            $schema['title'] = $this->label;
        }

        if (!empty($this->description)) {
            $schema['description'] = $this->description;
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

    /**
     * Returns the unique name of the container, which serves as its identifier.
     *
     * @return string The unique name of the container.
     */
    final public function getIdentifier(): string {
        return $this->getName();
    }
}