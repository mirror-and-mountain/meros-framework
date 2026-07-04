<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Support\ClassInfo;

abstract class Register {
    /**
     * The provider that the register is currently checked out to.
     *
     * @var FeatureProvider|null
     */
    protected ?FeatureProvider $provider = null;

    /**
     * The identifier for the features managed by this register.
     * This is used to ensure uniqueness and proper retrieval of features.
     *
     * @var string
     */
    protected string $identifier;

    /**
     * The fully qualified class name of the feature type that this register manages.
     *
     * @var string
     */
    protected string $definition;

    /**
     * Array of fully qualified class names of classes that can be attached to this register.
     * Each class should extend the register's definition class to ensure compatibility.
     * 
     * Alternatively, definitions may be registered with a callback that returns an instance of the definition
     * class when called.
     *
     * @var array<string|Closure>
     */
    protected array $registered = [];

    /**
     * List of supported operations for this register.
     *
     * @var array<string>
     */
    protected array $supports = [
        'register',
        'make',
        'makeFrom',
        'makeFromCallback',
        'public',
        'multiple'
    ];

    /**
     * List of operations that are not supported by this register.
     *
     * @var array<string>
     */
    protected array $rejects = [];

    /**
     * The collection of features registered in this register.
     *
     * @var Collection
     */
    protected Collection $instances;

    /**
     * Register constructor.
     * 
     */
    public function __construct() {
        $this->instances = collect([]);
    }

    /**
     * Checks out the register to a specific feature provider.
     *
     * @param FeatureProvider $provider The provider to check out the register to.
     * 
     * @return self The current register instance for chaining.
     */
    public function checkout(FeatureProvider $provider): self {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Determines if the register is currently checked out to a provider.
     * 
     * @param FeatureProvider|null $provider Optional provider to check against. If null, checks if the register is checked out to any provider.
     * 
     * @return void
     * @throws \LogicException If the register is not currently checked out to a provider.
     */
    protected function ensureCheckedOut(?FeatureProvider $provider = null): void {
        if ($provider !== null && $this->provider === null) {
            $this->checkout($provider);
            return;
        }

        if ($this->provider === null) {
            throw new \LogicException("The register (" . static::class . ") is not currently checked out to a provider.");
        }
    }

    /**
     * Checks the register back in, making it available for other providers to use.
     *
     * @return void
     */
    protected function checkin(): void {
        $this->provider = null;
    }

    /**
     * Determines if the register supports a specific operation.
     *
     * @param string $operation The operation to check support for.
     * 
     * @return bool True if the operation is supported, false otherwise.
     */
    protected function supports(string $operation): bool {
        if (in_array($operation, $this->rejects)) {
            return false;
        }

        return in_array($operation, $this->supports);
    }

    /**
     * Registers a feature class or callback to be available for attachment in this register.
     *
     * @param string               $id                     The identifier for the feature class or a callback to create the feature when required.
     * @param string|array|Closure $featureClassOrCallback The fully qualified class name of the feature or a callback to create it.
     * @param FeatureProvider|null $provider               Optional provider to check out the register to for this operation.
     *
     * @return void
     * @throws \BadMethodCallException If the register does not support registering feature classes.
     * @throws \InvalidArgumentException If the provided class does not extend the expected definition class or if it already exists in the register.
     */
    public function register(string $id, string|array|Closure $featureClassOrCallback, ?FeatureProvider $provider = null): void {
        if (!$this->supports('register')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support registering feature classes.");
        }

        if (is_callable($featureClassOrCallback)) {
            if (!$this->supports('makeFromCallback')) {
                throw new \BadMethodCallException("This register (" . static::class . ") does not support registering feature classes with a callback.");
            }
        }

        if (in_array($id, array_keys($this->registered))) {
            throw new \InvalidArgumentException("A class with the ID {$id} is already registered in this register (" . static::class . ").");
        }

        if (in_array($featureClassOrCallback, $this->registered)) {
            throw new \InvalidArgumentException("The class {$featureClassOrCallback} is already registered in this register (" . static::class . ").");
        }

        $this->ensureCheckedOut($provider);

        if (!is_callable($featureClassOrCallback)) {
            $class = ClassInfo::get($featureClassOrCallback);

            if (!$class->extends($this->definition)) {
                throw new \InvalidArgumentException("Class {$featureClassOrCallback} must extend {$this->definition} to be added to this register.");
            }
        }

        $this->registered[$id] = $featureClassOrCallback;
        $this->checkin();
    }

    /**
     * Retrieves all registered feature classes in this register.
     *
     * @return array An associative array of registered feature classes.
     */
    public function getRegistered(): array {
        if (!$this->supports('register')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support retrieving registered feature classes.");
        }
        
        return $this->registered;
    }

    /**
     * Creates a new feature and adds it to the register.
     *
     * @param Closure|array|null   $callback Optional callback to configure the feature.
     * @param array                $props Optional arguments for the feature's constructor.
     * @param FeatureProvider|null $provider Optional provider to check out the register to for this operation.
     *
     * @return FeatureDefinition The newly created feature instance.
     * @throws \BadMethodCallException If the register does not support making features.
     */
    public function make(Closure|array|null $callback = null, array $props = [], ?FeatureProvider $provider = null): FeatureDefinition {
        if (!$this->supports('make')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support making features.");
        }

        $this->ensureCheckedOut($provider);

        $params = func_num_args();

        if ($params === 1 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        $parsedProperties = $this->parseProperties($props);

        $item = new $this->definition(
            $this->provider,
            $parsedProperties
        );

        if ($callback && $callback instanceof Closure) {
            $callback($item);
        }

        $this->attach($item);
        $this->checkin();
        return $item;
    }

    /**
     * Creates a new feature from a registered class or callback and adds it to the register.
     *
     * @param string               $id        The identifier of the registered feature to instantiate.
     * @param Closure|array|null   $callback  Optional callback to configure the feature.
     * @param array                $props     Optional arguments for the feature's constructor.
     * @param FeatureProvider|null $provider  Optional provider to check out the register to for this operation.
     *
     * @return FeatureDefinition The newly created feature instance.
     * @throws \BadMethodCallException If the register does not support making features from registered classes or callbacks, or if the specified ID is not registered.
     * @throws \InvalidArgumentException If the registered class does not extend the expected definition class or if the callback does not return a valid feature instance.
     */
    public function makeFrom(string $id, Closure|array|null $callback = null, array $props = [], ?FeatureProvider $provider = null): FeatureDefinition {
        if (!$this->supports('makeFrom') && !$this->supports('makeFromCallback')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support making features from registered classes or callbacks.");
        }

        $this->ensureCheckedOut($provider);
        $provider = $this->provider;

        $params = func_num_args();

        if ($params === 2 && is_array($callback)) {
            $props    = $callback;
            $callback = null;
        }

        $class = null;
        $maker = null;
        $item  = null;

        if (!in_array($id, array_keys($this->registered))) {
            throw new \InvalidArgumentException("No item is registered with the ID {$id} in this register (" . static::class . ").");
        }

        if (is_callable($this->registered[$id])) {
            $maker = $this->registered[$id];
        } else {
            $class = $this->registered[$id];
        }

        if ($maker !== null && is_callable($maker)) {
            $item = $maker();
            $item->{$this->identifier} = $id; // Ensure the identifier is set on the item
        }

        else if ($class !== null) {
            $parsedProperties = $this->parseProperties($props);
            $item = $this->attach(new $class(
                $this->provider,
                $parsedProperties
            ));
        }

        if ($item === null) {
            throw new \BadMethodCallException("Could not create an instance of the item with ID {$id} in this register (" . static::class . ").");
        }

        $this->checkout($provider); // Re-checkout to ensure the provider is set after making the item

        if ($callback && $callback instanceof Closure) {
            $callback($item);
        }

        return $item;
    }

    /**
     * Adds an existing feature instance to the appropriate collection in the register.
     *
     * @param FeatureDefinition $feature The feature to add.
     * 
     * @return FeatureDefinition The feature that was added.
     */
    public function attach(FeatureDefinition $feature): FeatureDefinition {
        if (!$this->supports('multiple')) {
            $identifier = $feature->{$this->identifier};
            $existing = $this->getExistingInstance($identifier);

            if ($existing !== false) {
                return $existing;
            }
        }

        $this->instances->push($feature);

        return $feature;
    }

    /**
     * Retrieves a feature or collection of features from the register.
     *
     * @param string               $id       Optional identifier to retrieve a specific feature.
     * @param Closure|null         $callback Optional callback to filter or modify the retrieved feature(s).
     * @param FeatureProvider|null $provider Optional provider to check out the register to for this operation.
     * 
     * @return FeatureDefinition|Collection|null The requested feature, all the provider's features, or null if not found.
     */
    public function get(string $id = '', ?Closure $callback = null, ?FeatureProvider $provider = null): FeatureDefinition|Collection|null {
        // If the register supports public retrieval, search all instances regardless of provider
        if ($this->supports('public')) {
            // If no ID is provided, return all instances (optionally filtered by the callback)
            if ($id === '') {
                $items = $this->instances;

                if ($callback) {
                    $items->each($callback);
                }

                return $items;
            }

            // Search for the item by the identifier or nickname across all instances
            $item = $this->instances->firstWhere($this->identifier, $id);

            // If not found by identifier, try searching by nickname
            if ($item === null) {
                $item = $this->instances->firstWhere('nickname', $id);
            }

            // Check if the item is registered but not currently instantiated
            if ($item === null) {
                $registered = $this->registered[$id] ?? null;

                if ($registered) {
                    $item = $this->makeFrom($id);
                    $item->{$this->identifier} = $id; // Ensure the identifier is set on the item
                }
            }

            // If a callback is provided, execute it with the found item
            if ($item && $callback) {
                $callback($item);
            }

            return $item;
        }

        // If public retrieval is not supported, search instances from the current provider
        $this->ensureCheckedOut($provider);
        $provider = $this->provider;

        // If no ID is provided, return all instances for the current provider (optionally filtered by the callback)
        if ($id === '') {
            $items = $this->instances->where('provider', $this->provider);

            if ($callback) {
                $items->each($callback);
            }

            $this->checkin();
            return $items;
        }

        // Search for the item by the identifier field or nickname among instances from the current provider
        $item = $this->instances->where('provider', $this->provider)->firstWhere($this->identifier, $id);
        
        if ($item === null) {
            $item = $this->instances->where('provider', $this->provider)->firstWhere('nickname', $id);
        }

        if ($item === null) {
            $registered = $this->registered[$id] ?? null;

            if ($registered) {
                $item = $this->makeFrom($id);
                $item->{$this->identifier} = $id; // Ensure the identifier is set on the item
                $this->checkout($provider); // Re-checkout to ensure the provider is set after making the item
            }
        }

        // If a callback is provided, execute it with the found item
        if ($item && $callback) {
            $callback($item);
        }

        $this->checkin();
        return $item;
    }

    /**
     * If the register supports public retrieval, returns all features in the register. Otherwise, returns all features for the current provider.
     * 
     * @param FeatureProvider|null $provider Optional provider to check out the register to for this operation.
     *
     * @return Collection A collection of all features in the register.
     */
    public function all(?FeatureProvider $provider = null): Collection {
        if ($this->supports('public')) {
            return $this->instances;
        }

        $this->ensureCheckedOut($provider);
        
        $items = $this->instances->where('provider', $this->provider);
        $this->checkin();

        return $items;
    }

    /**
     * Parses the properties for creating a new feature instance.
     *
     * @param array $props The properties to parse.
     * 
     * @return array The parsed properties ready for the feature's constructor.
     */
    abstract protected function parseProperties(array $props): array;

    /**
     * Checks if a feature with the given identifier already exists in the register.
     *
     * @param string $id The identifier to check for.
     *
     * @return FeatureDefinition|false The existing feature instance if found, false otherwise.
     */
    private function getExistingInstance(string $id): FeatureDefinition|false {
        if ($id === '') {
            return false;
        }

        $instance = $this->get($id);

        if ($instance instanceof FeatureDefinition) {
            return $instance;
        }

        return false;
    }
}