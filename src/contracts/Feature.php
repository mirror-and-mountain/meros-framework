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
    public FeatureProvider $provider;

    /**
     * A nickname for the feature. Can be used to resolve the feature 
     * in situations where the identifier is not suitable.
     *
     * @var string
     */
    public string $nickname = '';

    /**
     * Array of properties passed to the feature.
     *
     * @var array
     */
    protected array $passedProps = [];

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
    protected bool $shared = false;

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
        $this->configure();

        if ($callbackOrProps instanceof Closure) {
            $callbackOrProps($this);
        }

        $this->whenConfigured();
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
     * Called after the feature has been configured. This method can be used to perform any additional actions after configuration.
     * Generally recommended for abstract subclasses to perform post-configuration tasks.
     *
     * @return void
     */
    protected function whenConfigured(): void {
        // This method can be overridden by subclasses to perform actions after configuration.
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
     * @return static
     */
    protected function set(string $key, mixed $value): static {
        if (method_exists($this, $key)) {
            $this->$key($value);
            return $this;
        }

        if (property_exists($this, $key)) {
            $this->$key = $value;
            return $this;
        }

        $camel = Str::camel($key);
        if (property_exists($this, $camel)) {
            $this->$camel = $value;
        }

        return $this;
    }

    /**
     * Sets properties on the feature instance based on the provided array.
     *
     * @param array $props  An associative array of properties to set on the feature instance.
     * @param array $ignore An array of property names to ignore when setting properties.
     * @param array $merge  An array of property names to merge with existing values instead of 
     *                      overwriting when the property is an array. If a key in this array does 
     *                      not exist in the $props array, it will be set to the value provided in this array.
     *
     * @return void
     */
    protected function setProps(array $props, array $ignore = [], array $merge = []): void {    
        foreach ($props as $key => $value) {
            if (in_array($key, $ignore)) {
                continue;
            }

            if (!is_array($value)) {
                $this->set($key, $value);
                continue;
            }

            if (is_array($value) && !in_array($key, $merge)) {
                $this->set($key, $value);
                continue;
            }

            if (is_array($value) && in_array($key, $merge)) {
                $existingValue = $this->$key ?? [];
                if (is_array($existingValue)) {
                    $mergedValue = array_merge($existingValue, $value);
                    $this->set($key, $mergedValue);
                }
            }
        }

        foreach ($merge as $key => $mergeValue) {
            if (!array_key_exists($key, $props)) {
                $this->set($key, $mergeValue);
            }
        }
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
     *
     * @return static
     */
    abstract public function setIdentifier(string $identifier): static;

    /**
     * Sets the feature's nickname.
     *
     * @param string $nickname The nickname to set.
     *
     * @return static
     */
    final public function nickname(string $nickname): static {
        $this->nickname = $nickname;
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
     * Returns the primary identifier for the feature. Should be implemented by subclasses to provide a unique identifier for the feature.
     *
     * @return string The unique identifier for the feature.
     */
    abstract public function getIdentifier(): string;
}