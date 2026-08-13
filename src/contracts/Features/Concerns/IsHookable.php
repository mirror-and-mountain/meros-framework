<?php 

namespace MM\Meros\Contracts\Features\Concerns;

trait IsHookable {
    /**
     * Whether the item is hookable.
     *
     * @var boolean
     */
    final protected bool $isHookable = true;

    /**
     * The WordPress hook to which the feature will be attached.
     * Should be set by the concrete class before the feature is hooked.
     *
     * @var array
     */
    private array $hook = [];

    /**
     * Whether the item has been hooked into WordPress.
     *
     * @var boolean
     */
    private bool $hooked = false;

    /**
     * Hooks the feature into a specific WordPress hook. Concrete classes should 
     * ensure the feature is ready before hooking. 
     *
     * @return void
     * @throws \LogicException if the hook property is not set with 'hook' and 'callback' keys before calling this method.
     */
    final protected function hook(): void {
        if ($this->hooked) {
            return;
        }

        if (empty($this->hook) || !isset($this->hook['hook'], $this->hook['callback'])) {
           throw new \LogicException("The hook property must be set with 'hook' and 'callback' keys before calling hook().");
        }

        if (!empty($this->hook)) {
            add_action(
                $this->hook['hook'],
                $this->hook['callback'],
                $this->hook['priority'],
                $this->hook['accepted_args']
            );
        }

        $this->hooked = true;
    }

    /**
     * Sets the WordPress hook to which the feature will be attached.
     *
     * @param string       $hook               The WordPress hook name.
     * @param callable|int $callbackOrPriority The callback function to be executed when the hook is triggered, or the priority if no callback is provided.
     * @param int          $priority           The priority at which the function should be executed. Default is 10.
     * @param int          $acceptedArgs       The number of arguments the function accepts. Default is 1.
     *
     * @return void
     */
    final protected function setHook(string $hook, callable|int $callbackOrPriority = 10, int $priority = 10, int $acceptedArgs = 1): void {
        if (is_int($callbackOrPriority)) {
            $priority = $callbackOrPriority;
            $callback = [$this, 'defaultHookCallback'];
        } else {
            $callback = $callbackOrPriority;
        }

        $this->hook = [
            'hook'          => $hook,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $acceptedArgs
        ];
    }

    /**
     * The default callback function to be executed when the feature is hooked.
     * Concrete classes should override this method to define their specific behavior.
     *
     * @return void
     */
    public function defaultHookCallback(): void {
        // Default implementation can be overridden by concrete classes.
    }

    /**
     * Returns whether the feature has been hooked into WordPress.
     *
     * @return boolean
     */
    public function isHooked(): bool {
        return $this->hooked;
    }
}