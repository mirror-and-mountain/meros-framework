<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;

abstract class Feature  {
    /**
     * The provider that registered the feature.
     *
     * @var FeatureProvider
     */
    protected FeatureProvider $provider;

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

        foreach ($props as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }

            $camel = Str::camel($key);
            if (property_exists($this, $camel)) {
                $this->$camel = $value;
            }
        }

        $this->setReady();
    }

    /**
     * Method to load the feature by hooking it into WordPress.
     * 
     * @return void
     */
    abstract protected function load(): void;

    /**
     * Sets the feature as ready (or not) based on the state of the feature's current configuration.
     *
     * @return void
     */
    abstract protected function setReady(): void;

    /**
     * Converts a callable to a Closure instance.
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