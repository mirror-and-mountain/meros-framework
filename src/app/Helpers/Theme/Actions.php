<?php 

namespace MM\Meros\App\Helpers\Theme;

abstract class Actions {
    /**
     * The prefix for the action hooks. Should be set to the
     * feature's hook prefix to avoid naming conflicts.
     * 
     * @var string
     */
    protected string $hookPrefix;

    /**
     * Initialises the Actions class with the given hook prefix.
     *
     * @param string $hookPrefix The prefix for the action hooks.
     * @return self The initialised Actions instance.
     */
    final public static function init(string $hookPrefix): static {
        $instance = new static();
        $instance->hookPrefix = $hookPrefix;
        return $instance;
    }

    /**
     * Registers all actions. Should be called in the feature's boot method.
     * 
     * @return void
     */
    public abstract function register(): void;

    /**
     * Utility method for adding actions using the Add helper.
     *
     * @param string $hook
     * @param callable|array $callback
     * @param integer $priority
     * @param integer $acceptedArgs
     * @return void
     */
    protected function add(string $hook, callable|array $callback, int $priority = 10, int $acceptedArgs = 1): void {
        Add::action($hook, $callback, $priority, $acceptedArgs);
    }
}
