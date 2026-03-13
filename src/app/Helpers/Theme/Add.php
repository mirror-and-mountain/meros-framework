<?php

namespace MM\Meros\App\Helpers\Theme;

class Add {
    /**
     * Adds an action using WP's add_action function.
     *
     * @param string $hook
     * @param callable $callback
     * @param integer $priority
     * @param integer $acceptedArgs
     * @return void
     */
    public static function action(
        string $hook,
        callable|array $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Adds a filter using WP's add_filter function.
     *
     * @param string $hook
     * @param callable|array $callback
     * @param integer $priority
     * @param integer $acceptedArgs
     * @return void
     */
    public static function filter(
        string $hook,
        callable|array $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }
}