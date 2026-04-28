<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;

abstract class FeatureDefinition {
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
     * Indicates that the feature's configuration is valid and
     * that the feature is ready to be hooked into WordPress.
     *
     * @var boolean
     */
    public bool $ready = false;

    /**
     * Indicates that the feature has been hooked into WordPress via the load() method.
     *
     * @var boolean
     */
    public bool $loaded = false;
    

    /**
     * Constructor for the Feature class.
     *
     * @param FeatureProvider $provider The provider that registered the feature.
     * @param array           $props    Optional properties to set on the feature.
     */
    public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        $this->setProps($props);
    }

    /**
     * Method to load the feature by hooking it into WordPress.
     * 
     * @return void
     */
    abstract protected function load(): void;

    /**
     * Sets the feature as ready (or not) based on the state of the feature's current configuration.
     * If the feature is ready, it should be hooked into WordPress via the load() method.
     *
     * @return void
     */
    abstract protected function hook(): void;

    /**
     * Configures the current item using a callback function.
     * 
     * @param Closure|null $callback Callback to configure the instance.
     *
     * @return self
     */
    final public function configure(Closure $callback): self {
        $callback($this);
        return $this;
    }

    /**
     * Checks if the feature is ready to be loaded.
     *
     * @return bool True if the feature is ready, false otherwise.
     */
    final public function isReady(): bool {
        return $this->ready;
    }

    /**
     * Sets properties on the feature instance based on the provided array.
     *
     * @param array $props An associative array of properties to set on the feature instance.
     *
     * @return void
     */
    protected function setProps(array $props): void {
        foreach ($props as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }

            $camel = Str::camel($key);
            if (property_exists($this, $camel)) {
                $this->$camel = $value;
            }
        }

        $this->hook();
    }

    /**
     * Checks if the feature has been loaded.
     *
     * @return bool True if the feature is loaded, false otherwise.
     */
    final public function isLoaded(): bool {
        return $this->loaded;
    }

    /**
     * Sets the feature's nickname.
     *
     * @param string $nickname The nickname to set.
     *
     * @return self
     */
    final public function nickname(string $nickname): self {
        $this->nickname = $nickname;
        return $this;
    }

    /**
     * Helper to convert a callable to a Closure instance.
     *
     * @param  callable|Closure $callback The callback to convert.
     * 
     * @return Closure|false The converted Closure instance, or false if the input is not callable.
     */
    protected function convertToClosure(callable|Closure $callback): Closure|false {
        if ($callback instanceof Closure) {
            return $callback;
        } elseif (is_callable($callback)) {
            return Closure::fromCallable($callback);
        } else {
            return false;
        }
    }
}