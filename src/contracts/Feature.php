<?php 

namespace MM\Meros\Contracts;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\FeatureDefinition;
use MM\Meros\Contracts\Providers\FeatureProvider;

abstract class Feature implements FeatureDefinition {
    /**
     * The provider that registered the feature.
     *
     * @var FeatureProvider
     */
    private FeatureProvider $provider;

    /**
     * The name of the property used as the feature's identifier.
     *
     * @var string
     */
    private string $identifier = '';

    /**
     * The default format of the feature's identifier. May be set to 'slug' or 'snake';
     *
     * @var string
     */
    private string $defaultIdentifierFormat = '';

    /**
     * The feature's label.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The feature's description.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Array of properties passed to the feature.
     *
     * @var array
     */
    protected array $passedProps = [];

    /**
     * Indicates whether all properties should be ignored when setting properties on the feature instance.
     *
     * @var boolean
     */
    private bool $ignoreAllProps = false;

    /**
     * An array of properties to be ignored when setting properties on the feature instance.
     *
     * @var array
     */
    private array $ignoredProps = [];

    /**
     * An array of properties that are allowed to be set on the feature instance via the passedProps array.
     *
     * @var array
     */
    private array $allowedProps = [];

    /**
     * An array of properties to be merged with existing values on the feature instance.
     *
     * @var array
     */
    private array $mergeProps = [];

    /**
     * Array of context data for the feature.
     *
     * @var array
     */
    protected array $context = [];

    /**
     * The creation method used to instantiate the feature.
     *
     * @var string
     */
    protected string $creationMethod = '';

    /**
     * Indicates whether the feature is available to all providers or only to the provider that registered it.
     * Only relevant if the feature's register is set to 'private'. 
     * 
     * This property will override the register's privacy setting if set to true.
     *
     * @var boolean
     */
    private bool $shared = false;

    // =========================================================================
    // Initialisation
    // =========================================================================
    
    /**
     * Constructor for the Feature class.
     *
     * @param FeatureProvider $provider        The provider that registered the feature.
     * @param Closure|array   $callbackOrProps An optional callback to modify the feature instance after creation, or an array of properties to be passed to the 'passedProps' property of the feature instance.
     * @param array           $props           An array of properties to be passed to the 'passedProps' property of the feature instance.
     */
    final protected function __construct(
        FeatureProvider $provider,
        Closure|array   $callbackOrProps = [],
        array           $props = [],
        array           $context = []
    ) {
        $this->provider = $provider;

        if (is_array($callbackOrProps)) {
            $props = array_merge($props, $callbackOrProps);
        }

        $this->passedProps    = $props;
        $this->context        = $context;
        $this->creationMethod = $context['creation_method'] ?? '';
        
        $this->init();

        if (empty($this->identifier)) {
            throw new \RuntimeException("Feature identifier not set. Please call the 'identifier()' method in the init() method of the feature class.");
        }

        if ($this->ignoreAllProps === false) {
            $this->setProps();
        }

        $this->configure();

        if ($callbackOrProps instanceof Closure) {
            $callbackOrProps($this);
        }

        $this->__whenConfigured();
    }

    /**
     * Initialises the feature. This method is called after the constructor and can be used to perform any additional setup.
     * Generally recommended for abstract subclasses to perform initialisation tasks, while concrete subclasses should use the configure() method for configuration.
     *
     * @return void
     */
    protected function init(): void {
        // This method can be overridden by subclasses to perform additional initialisation.
    }

    /**
     * Configures the feature. This method is called after the init() method and can be used to perform any additional configuration.
     * Generally recommended for concrete subclasses to perform configuration tasks, while abstract subclasses should use the init() method for initialisation.
     *
     * @return void
     */
    protected function configure(): void {
        // This method can be overridden by subclasses to perform additional configuration.
    }

    /**
     * Called after the feature has been configured. 
     *
     * @return void
     */
    private function __whenConfigured(): void {
        if (empty($this->label)) {
            $this->label = Str::title(Str::replace(['-', '_'], ' ', $this->getIdentifier()));
        }

        $this->whenConfigured();
    }

    /**
     * Called after the feature has been configured. This method can be used to perform any additional actions after configuration.
     * Generally recommended for abstract subclasses to perform post-configuration tasks.
     *
     * @return void
     */
    protected function whenConfigured(): void {
        // This method can be overridden by subclasses to perform actions after configuration.
    }

    /**
     * Sets the property name that will be used as the feature's identifier. This method should be called in the feature's init() method.
     *
     * @param string $identifier    The property name that will be used as the feature's identifier. This property must exist on the feature instance.
     * @param string $defaultFormat The default format of the identifier. Can be 'slug' or 'snake'.
     *
     * @return void
     */
    final protected function identifier(string $identifier, string $defaultFormat): void {
        if (!property_exists($this, $identifier)) {
            throw new \InvalidArgumentException("Property '{$identifier}' does not exist on class " . static::class);
        }

        if (!in_array($defaultFormat, ['slug', 'snake'])) {
            throw new \InvalidArgumentException("Default identifier format must be either 'slug' or 'snake'.");
        }

        $this->identifier = $identifier;
        $this->defaultIdentifierFormat = $defaultFormat;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets a property on the feature instance.
     *
     * @param string $key   The property name to set.
     * @param mixed  $value The value to assign to the property.
     *
     * @return void
     */
    private function setProp(string $key, mixed $value): void {
        if (method_exists($this, $key)) {
            $this->$key($value);
        }

        if (property_exists($this, $key)) {
            $this->$key = $value;
        }

        $camel = Str::camel($key);
        if (property_exists($this, $camel)) {
            $this->$camel = $value;
        }
    }

    /**
     * Sets properties on the feature instance based on the passed properties
     * It ignores any properties specified in the ignoredProps array and 
     * merges any properties specified in the mergeProps array.
     *
     * @return void
     */
    private function setProps(): void {
        $props = empty($this->allowedProps) 
            ? $this->passedProps 
            : array_intersect_key($this->passedProps, array_flip($this->allowedProps));

        foreach ($props as $key => $value) {
            if (in_array($key, $this->ignoredProps)) {
                continue;
            }
            
            $camel = Str::camel($key);
            if (in_array($camel, $this->ignoredProps)) {
                continue;
            }

            $property = property_exists($this, $key) ? $key : (property_exists($this, $camel) ? $camel : null);
            $propertyExists = $property !== null;

            if (in_array($key, $this->mergeProps) &&
                $propertyExists &&
                is_array($value)
            ) {
                $existingValue = $this->$property ?? [];
                $mergedValue   = array_merge($existingValue, $value);
                $this->setProp($property, $mergedValue);
            }

            if ($propertyExists) {
                $this->setProp($property, $value);
            }
        }
    }

    /**
     * Sets a property on the feature instance. If a method with the same name as the property exists, 
     * it will be called with the value as an argument. Otherwise, the property will be set directly.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    final protected function set(string $key, mixed $value): void {
        $this->setProp($key, $value);
    }

    /**
     * Defines a list of properties to be merged with existing values on the 
     * feature instance based on the provided array of passed properties.
     * 
     * Should be called in the feature's init() method to specify which properties should be merged rather than overwritten.
     *
     * @param array $props
     *
     * @return void
     */
    final protected function mergeProps(array $props): void {
        $this->mergeProps = array_merge($this->mergeProps, $props);
    }

    /**
     * Defines a list of properties that are allowed to be set on the feature instance via the passedProps array.
     * 
     * Should be called in the feature's init() method to specify which properties are allowed to be set on the feature instance.
     *
     * @param array $props
     *
     * @return void
     */
    final protected function allowedProps(array $props): void {
        $this->allowedProps = array_merge($this->allowedProps, $props);
    }

    /**
     * Defines a list of properties to be ignored when setting properties on the feature instance.
     * 
     * Should be called in the feature's init() method to specify which properties should be ignored when setting properties on the feature instance.
     *
     * @param array $props
     *
     * @return void
     */
    final protected function ignoreProps(array $props): void {
        $this->ignoredProps = array_merge($this->ignoredProps, $props);
    }

    /**
     * Sets whether all properties should be ignored when setting properties on the feature instance.
     * 
     * Should be called in the feature's init() method to specify whether all properties should be ignored when setting properties on the feature instance.
     *
     * @param bool $ignore
     *
     * @return void
     */
    final protected function ignoreAllProps(bool $ignore = true): void {
        $this->ignoreAllProps = $ignore;
    }

    /**
     * Sets the provider that registered the feature.
     *
     * @param FeatureProvider $provider The provider to set.
     *
     * @return static
     */
    final public function setProvider(FeatureProvider $provider): static {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Sets the feature's identifier. This method should be implemented by subclasses to define how the identifier is set.
     *
     * @param string $identifier The identifier to set for the feature.
     * @param bool   $returnValue Whether to return the identifier value instead of the feature instance. Defaults to false.
     *
     * @return string|static The feature instance or the identifier value, depending on the $returnValue parameter.
     */
    final public function setIdentifier(string $identifier, bool $returnValue = true): string|static {
        if (!property_exists($this, $this->identifier)) {
            throw new \InvalidArgumentException("Property '{$this->identifier}' does not exist on class " . static::class);
        }

        if (empty($this->defaultIdentifierFormat)) {
            throw new \RuntimeException("Default identifier format not set. Please call the 'identifier()' method in the init() method of the feature class.");
        }

        $this->{$this->identifier} = $this->defaultIdentifierFormat === 'slug' 
            ? Str::slug(Str::replace('_', '-', $identifier)) 
            : Str::snake(Str::replace('-', '_', $identifier));

        return $returnValue ? $this->{$this->identifier} : $this;
    }

    /**
     * Sets the label of the feature.
     *
     * @param string $label
     *
     * @return static
     */
    public function label(string $label): static {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the description of the feature.
     *
     * @param string $description
     *
     * @return static
     */
    final public function description(string $description): static {
        $this->description = wp_kses_post($description);
        return $this;
    }

    /**
     * Sets whether the feature is shared across all providers or only available to the provider that registered it.
     *
     * @param bool $shared True if the feature should be shared, false otherwise.
     *
     * @return static
     */
    final protected function share(bool $shared = true): static {
        $this->shared = $shared;
        return $this;
    }

    /**
     * Adds context data to the feature instance.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return static
     */
    final public function addContext(string $key, mixed $value): static {
        $this->context[$key] = $value;
        $this->whenContextAdded($key, $value);
        return $this;
    }

    /**
     * Called when context is added via the addContext method. May be used by concrete classes if needed.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    protected function whenContextAdded(string $key, mixed $value): void {

    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the provider that registered the feature.
     *
     * @return FeatureProvider The provider this feature belongs to.
     */
    final public function getProvider(): FeatureProvider {
        return $this->provider;
    }


    /**
     * Returns whether the feature is shared across all providers or only available to the provider that registered it.
     *
     * @return bool True if the feature is shared, false otherwise.
     */
    final public function isShared(): bool {
        return $this->shared;
    }

    /**
     * Returns the feature's context data.
     *
     * @return mixed The context data for the feature. If a key is provided, returns the value for that key or the default value if the key does not exist.
     */
    final public function getContext(string $key = '', mixed $default = ''): mixed {
        if ($key === '') {
            return $this->context;
        }

        return $this->context[$key] ?? $default;
    }

    /**
     * Returns the primary identifier for the feature. Should be implemented by subclasses to provide a unique identifier for the feature.
     * 
     * @param string $format The format of the identifier to return. Can be 'default', 'slug', or 'snake'. Defaults to 'default'.
     *
     * @return string The unique identifier for the feature.
     */
    final public function getIdentifier(string $format = 'default'): string {
        if (empty($this->identifier)) {
            throw new \RuntimeException("Feature identifier not set. Please call the 'identifier()' method in the init() method of the feature class.");
        }

        $value = $this->{$this->identifier};

        return match ($format) {
            'slug'  =>  $this->defaultIdentifierFormat === 'snake' ? Str::slug(Str::replace('_', '-', $value)) : $value,
            'snake' => $this->defaultIdentifierFormat === 'slug' ? Str::snake(Str::replace('-', '_', $value)) : $value,
            default => $value
        };
    }

    /**
     * Returns the default format of the feature's identifier.
     *
     * @return string The default format of the feature's identifier. Can be 'slug' or 'snake'.
     */
    final public function getDefaultIdentifierFormat(): string {
        return $this->defaultIdentifierFormat;
    }

    /**
     * Returns the label of the feature.
     *
     * @return string The label of the feature.
     */
    final public function getLabel(): string {
        return $this->label;
    }

    /**
     * Returns the description of the feature.
     *
     * @return string The description of the feature.
     */
    final public function getDescription(): string {
        return $this->description;
    }
}