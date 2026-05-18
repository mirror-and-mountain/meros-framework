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
     * Array of arguments for the item.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * Indicates that the feature has been queued for loading via a WordPress hook.
     *
     * @var boolean
     */
    protected bool $queued = false;

    /**
     * Whether to automatically queue the feature after setting properties.
     *
     * @var boolean
     */
    protected bool $autoQueue = true;

    /**
     * The output of the function used to load the feature, if applicable.
     *
     * @var boolean
     */
    protected mixed $WpMessage = null;
    
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
     * Queues the feature to be loaded on a specific WordPress hook. Concrete classess should 
     * ensure the feature is ready before queuing. They should also set the $queued property 
     * to true once the feature is successfully queued.
     *
     * @return void
     */
    abstract protected function queue(): void;

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
     * Returns whether the feature has been queued for loading.
     *
     * @return bool True if the feature has been queued, false otherwise.
     */
    final public function isQueued(): bool {
        return $this->queued;
    }

    /**
     * Returns the output of the function used to load the feature, if applicable.
     *
     * @return mixed The output of the function used to load the feature, or null if not set.
     */
    final public function getWpMessage(): mixed {
        return $this->WpMessage;
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
            if ($key === 'args' && is_array($value)) {
                $this->args = array_merge($this->args ?? [], $value);
                continue;
            }

            if ($key === 'autoQueue' || $key === 'auto_queue') {
                $this->autoQueue = (bool) $value;
                continue;
            }

            if (property_exists($this, $key)) {

                if (isset($this->$key) && !empty($this->$key)) {
                    continue; // Skip setting the property if it already has a non-empty value
                }

                $this->$key = $value;
            }

            $camel = Str::camel($key);
            if (property_exists($this, $camel)) {
                
                if (isset($this->$camel) && !empty($this->$camel)) {
                    continue; // Skip setting the property if it already has a non-empty value
                }

                $this->$camel = $value;
            }
        }

        if ($this->autoQueue) {
            $this->queue();
        }
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
     * Returns the provider that registered the feature.
     *
     * @return FeatureProvider The provider this feature belongs to.
     */
    final public function provider(): FeatureProvider {
        return $this->provider;
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