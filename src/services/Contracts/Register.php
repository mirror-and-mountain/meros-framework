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
     * @var array<string>
     */
    protected array $classes = [];

    /**
     * List of supported operations for this register.
     *
     * @var array<string>
     */
    protected array $supports = [
        'register',
        'make',
        'makeFrom',
        'attach',
        'get',
        'all',
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
     * @return void
     * @throws \LogicException If the register is not currently checked out to a provider.
     */
    protected function ensureCheckedOut(): void {
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
     * Registers a feature class to be available for attachment in this register.
     *
     * @param string $id           The identifier for the feature class.
     * @param string $featureClass The fully qualified class name of the feature.
     *
     * @return void
     * @throws \BadMethodCallException If the register does not support registering feature classes.
     * @throws \InvalidArgumentException If the provided class does not extend the expected definition class or if it already exists in the register.
     */
    public function register(string $id, string $featureClass): void {
        if (!$this->supports('register')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support registering feature classes.");
        }

        if (in_array($id, array_keys($this->classes))) {
            throw new \InvalidArgumentException("A class with the ID {$id} is already registered in this register (" . static::class . ").");
        }

        if (in_array($featureClass, $this->classes)) {
            throw new \InvalidArgumentException("The class {$featureClass} is already registered in this register (" . static::class . ").");
        }

        $this->ensureCheckedOut();

        $class = ClassInfo::get($featureClass);

        if (!$class->extends($this->definition)) {
            throw new \InvalidArgumentException("Class {$featureClass} must extend {$this->definition} to be added to this register.");
        }

        $this->classes[$id] = $featureClass;
        $this->checkin();
    }

    /**
     * Creates a new feature and adds it to the register.
     *
     * @param Closure|array|null $callback Optional callback to configure the feature.
     * @param array              $props Optional arguments for the feature's constructor.
     *
     * @return FeatureDefinition The newly created feature instance.
     * @throws \BadMethodCallException If the register does not support making features.
     */
    public function make(Closure|array|null $callback = null, array $props = []): FeatureDefinition {
        if (!$this->supports('make')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support making features.");
        }

        $this->ensureCheckedOut();

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
     * Adds a new feature to the register using the make() method.
     *
     * @param Closure|array|null $callback Optional callback to configure the feature.
     * @param array              $props Optional arguments for the feature's constructor.
     *
     * @return FeatureDefinition The newly created feature instance.
     * @throws \BadMethodCallException If the register does not support making features.
     */
    public function add(Closure|array|null $callback = null, array $props = []): FeatureDefinition {
        if (!$this->supports('make')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support making features.");
        }

        return $this->make($callback, $props);
    }

    /**
     * Creates a new feature from a registered class and adds it to the register.
     *
     * @param string $idOrClass The identifier or fully qualified class name to instantiate.
     * @param array  $props     Optional arguments for the feature's constructor.
     *
     * @return FeatureDefinition The newly created feature instance.
     * @throws \BadMethodCallException If the register does not support making features from registered classes.
     */
    public function makeFrom(string $idOrClass, array $props = []): FeatureDefinition {
        if (!$this->supports('makeFrom')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support making features from registered classes.");
        }

        $this->ensureCheckedOut();
        $provider = $this->provider;

        if (isset($this->classes[$idOrClass])) {
            $class = $this->classes[$idOrClass];
        } 

        else if (!in_array($idOrClass, $this->classes)) {
            $class = $idOrClass;
            $this->register(strtolower(class_basename($class)), $class);
            $this->checkout($provider); // Re-checkout to ensure the provider is set after registration
        }

        else {
            $class = $idOrClass;
        }

        $parsedProperties = $this->parseProperties($props);

        return $this->attach(new $class(
            $this->provider,
            $parsedProperties
        ));
    }

    /**
     * Adds an existing feature instance to the appropriate collection in the register.
     *
     * @param FeatureDefinition $feature The feature to add.
     * 
     * @return FeatureDefinition The feature that was added.
     * @throws \BadMethodCallException If the register does not support attaching features.
     */
    public function attach(FeatureDefinition $feature): FeatureDefinition {
        if (!$this->supports('attach')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support attaching features.");
        }

        $this->ensureCheckedOut();

        if (!$this->supports('multiple')) {
            $identifier = $feature->{$this->identifier};
            $existing = $this->getExistingInstance($identifier);

            if ($existing !== false) {
                $this->checkin();
                return $existing;
            }
        }

        $this->instances->push($feature);

        $this->checkin();
        return $feature;
    }

    /**
     * Retrieves a feature or collection of features from the register.
     *
     * @param string       $id The identifier to retrieve a specific feature.
     * @param Closure|null $callback Optional callback to filter or modify the retrieved feature(s).
     * 
     * @return FeatureDefinition|null The requested feature or null if not found.
     * @throws \BadMethodCallException If the register does not support retrieving features.
     */
    public function get(string $id, ?Closure $callback = null): FeatureDefinition|null {
        if (!$this->supports('get')) {
            throw new \BadMethodCallException("This register does not support retrieving features.");
        }

        // If the register supports public retrieval, search all instances regardless of provider
        if ($this->supports('public')) {
            $item = $this->instances->firstWhere($this->identifier, $id);

            if ($item === null) {
                $item = $this->instances->firstWhere('nickname', $id);
            }

            if ($item && $callback) {
                $callback($item);
            }

            return $item;
        }

        // Otherwise, only search instances from the current provider
        $this->ensureCheckedOut();

        $item = $this->instances->where('provider', $this->provider)->firstWhere($this->identifier, $id);
        
        if ($item === null) {
            $item = $this->instances->where('provider', $this->provider)->firstWhere('nickname', $id);
        }

        if ($item && $callback) {
            $callback($item);
        }

        $this->checkin();
        return $item;
    }

    /**
     * Retrieves all features from the register.
     * 
     * @param bool $currentProvider Whether to retrieve only features from the current provider or all features in the register.
     *
     * @return Collection A collection of all features in the register.
     * @throws \BadMethodCallException If the register does not support retrieving all features.
     */
    public function all(bool $currentProvider = true): Collection {
        if (!$this->supports('all')) {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support retrieving all features.");
        }

        if ($currentProvider) {
            $this->ensureCheckedOut();

            $items = $this->instances->where('provider', $this->provider);

            $this->checkin();
            return $items;
        }

        else if ($this->supports('public')) {
            return $this->instances;
        }

        else {
            throw new \BadMethodCallException("This register (" . static::class . ") does not support retrieving all features publicly.");
        }
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
        $provider = $this->provider;
        $instance = $this->get($id);
        $this->checkout($provider); // Re-checkout to ensure the provider is set after retrieval

        if ($instance) {
            return $instance;
        }

        return false;
    }
}