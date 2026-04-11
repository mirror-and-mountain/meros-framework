<?php 

namespace MM\Meros\App\Support;

use Closure;
use MM\Meros\App\Facades\Registry;

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
     * Add the feature to the registry.
     *
     * @return void
     */
    protected function addToRegistry(): void {
        Registry::add(strtolower(class_basename($this)), $this);
    }

    /**
     * Verifies that the given handle is unique across all features of the same type.
     *
     * @param  string $handle The handle to verify.
     * 
     * @return bool   True if the handle is unique, false otherwise.
     */
    private function handleIsUnique(string $handle): bool {
        $type = class_basename($this);
    
        $existingHandles = Registry::get(strtolower($type) . 's')
            ->pluck('handle')
            ->toArray();

        if (in_array($handle, $existingHandles)) {
            $this->error = "The handle '{$handle}' is already in use for another " . $type . ". Handles must be unique.";
            return false;
        }

        return true;
    }

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