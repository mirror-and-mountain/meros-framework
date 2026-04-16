<?php 

namespace MM\Meros\App\Support;

use Closure;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Contracts\FeatureBuilder;

abstract class Feature implements FeatureBuilder {
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
     * Error message if the configuration is invalid.
     *
     * @var string
     */
    public string $error = '';

    /**
     * Method to load the feature by hooking it into WordPress.
     * 
     * @param  Feature $instance The instance of the feature to load.
     *
     * @return void
     */
    abstract protected function load(Feature $instance): void;

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