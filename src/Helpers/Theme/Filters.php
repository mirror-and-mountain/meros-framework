<?php 

namespace MM\Meros\Helpers\Theme;

abstract class Filters {
    /**
     * The prefix for the filter hooks. Should be set to the
     * feature's hook prefix to avoid naming conflicts.
     * 
     * @var string
     */
    protected string $hookPrefix;

    /**
     * Initialises the Filters class with the given hook prefix.
     *
     * @param string $hookPrefix The prefix for the filter hooks.
     * @return self The initialised Filters instance.
     */
    final public static function init(string $hookPrefix): static {
        $instance = new static();
        $instance->hookPrefix = $hookPrefix;
        return $instance;
    }

    /**
     * Registers all filters. Should be called in the feature's boot method.
     * 
     * @return void
     */
    public abstract function register(): void;

    /**
     * Utility method for adding filters using the Add helper.
     *
     * @param string $hook
     * @param callable|array $callback
     * @param integer $priority
     * @param integer $acceptedArgs
     * @return void
     */
    protected function add(string $hook, callable|array $callback, int $priority = 10, int $acceptedArgs = 1): void {
        Add::filter($hook, $callback, $priority, $acceptedArgs);
    }
}
