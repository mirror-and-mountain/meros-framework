<?php

namespace MM\Meros\App\Services\Theme\Concerns;

use MM\Meros\App\Services\Theme\Concerns\HasSettings;
use MM\Meros\App\Services\Theme\Concerns\HasAssets;
use MM\Meros\App\Services\Theme\Concerns\HasBlocks;
use MM\Meros\App\Services\Theme\Concerns\HasComponents;
use MM\Meros\App\Services\Theme\Concerns\HasInstallables;

use MM\Meros\App\Services\Theme\Package;

trait HasFeatures {
    /**
     * Whether the item's hookFeatures() method has been called.
     * 
     * @var bool
     */
    public bool $initialised = false;

    /**
     * Indicates whether the item has installables.
     * 
     * @var boolean
     */
    private bool $hasInstallables = false;

    use HasSettings, HasAssets, HasBlocks, HasComponents, HasInstallables;

    /**
     * Used by child classes to add pre-load tasks, such as actions, filters.
     * Class properties can also be overridden here.
     *
     * @return void
     */
    protected function configure(): void {
        // Intentionally left blank for child classes to override.
    }

    /**
     * Used by child classes to register settings.
     *
     * @return void
     */
    protected function registerSettings(): void {
        // Intentionally left blank for child classes to override.
    }

    /**
     * Used by child classes to register features.
     * E.g. assets, blocks, components.
     *
     * @return void
     */
    protected function loadFeatures(): void {
        // Intentionally left blank for child classes to override.
    }

    /**
     * Used by child classes to register installables.
     * E.g. Migrations, seeders.
     *
     * @return void
     */
    protected function registerInstallables(): void {
        // Intentionally left blank for child classes to override.
    }

    /**
     * Helper method to add a filter.
     *
     * @param string $hook The name of the filter hook.
     * @param string|array|callable $callback The callback function or method.
     * @param int $priority The priority of the filter (default: 10).
     * @param int $acceptedArgs The number of accepted arguments (default: 1).
     * @return void
     */
    protected function addFilter(
        string $hook, 
        string|array|callable $callback, 
        int $priority = 10, 
        int $acceptedArgs = 1
    ): void {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Helper method to add an action.
     *
     * @param string $hook The name of the action hook.
     * @param string|array|callable $callback The callback function or method.
     * @param int $priority The priority of the action (default: 10).
     * @param int $acceptedArgs The number of accepted arguments (default: 1).
     * @return void
     */
    protected function addAction(
        string $hook, 
        string|array|callable $callback, 
        int $priority = 10, 
        int $acceptedArgs = 1
    ): void {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Prepares and hooks the item's features into
     * the Wordpress lifecycle.
     * 
     * @return void
     */
    final public function hookFeatures(): void {
        // Stop if the package isn't enabled
        if ($this instanceof Package &&
            $this->enabled === false
        ) {
            return;
        }

        if ($this->hasAssets) {
            $this->enqueueAssets();
        }

        if ($this->hasBlocks) {
            $this->registerBlocks();
        }

        if ($this->hasComponents) {
            $this->bindComponents();
            $this->bindViews();
        }

        $this->initialised = true;
    }

    /**
     * This method is called after all features have been
     * hooked.
     * 
     * @return void
     */
    public function runAfterHookFeatures(): void {
        // Intentionally left blank for child classes to override.
    }
}