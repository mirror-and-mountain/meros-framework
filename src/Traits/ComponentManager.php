<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use MM\Meros\Helpers\ClassInfo;

trait ComponentManager {
    /**
     * Indicates whether the feature has components.
     * Note: by component we mean a Livewire component.
     */
    private bool $hasComponents = false;

    /**
     * Indicates whether the feature has views.
     */
    private bool $hasViews = false;

    /**
     * The components directory relative to the feature
     * directory.
     */
    protected string $componentsDir = 'components';

    /**
     * The views directory relative to the feature
     * directory.
     */
    protected string $viewsDir = 'views';

    /**
     * Discovered components.
     */
    protected array $components = [];

    /**
     * Discovered views.
     */
    protected array $views = [];

    /**
     * Determines whether component handles should use
     * the feature's fullName. This can be useful if
     * the feature has a common name and we need to
     * avoid conflicts.
     */
    protected bool $useFullNameForComponents = false;

    /**
     * Sets absolute path and calls setComponents.
     */
    protected function loadComponents(): void {
        $componentsPath = $this->path . $this->componentsDir;
        $this->findComponents($componentsPath);
    }

    /**
     * Sets absolute path and calls setViews.
     */
    protected function loadViews(): void {
        $viewsPath = $this->path . $this->viewsDir;
        $this->findViews($viewsPath);
    }

    /**
     * Uses glob to search the given path for valid components.
     * Components will be indentified as a php file that contains
     * a class extending Livewire\\Component.
     */
    private function findComponents(string $path): void {
        if (! File::exists($path)) {
            return;
        }

        $candidates = File::glob($path . '/*.php');

        foreach ($candidates as $component) {

            $class = ClassInfo::getFromPath($component);

            if ($class->extends(Component::class)) {
                $handle = $this->useFullNameForComponents ? $this->fullName : $this->name;
                $handle .= '.' . Str::lower(Str::replace('.php', '', basename($component)));
                $this->addComponent($handle, $class);
            }
        }

        $this->hasComponents = $this->components !== [];
    }

    /**
     * Uses glob to search the given path for valid views.
     * Views will be identified as files with a blade.php extension.
     */
    private function findViews(string $path): void {
        if (! File::exists($path)) {
            return;
        }

        $candidates = File::glob($path . '/*.blade.php');

        foreach ($candidates as $view) {
            $handle = $this->useFullNameForComponents ? $this->fullName : $this->name;
            $this->addView($handle, $view);
        }

        $this->hasViews = $this->views !== [];
    }

    /**
     * Registers a Livewire component.
     */
    protected function addComponent(string $handle, object $class): void {
        $this->components[$handle] = $class->name;
    }

    /**
     * Registers a Blade view.
     */
    protected function addView(string $handle, string $path): void {
        $this->views[$handle] = $path;
    }

    /**
     * Binds discovered Livewire components.
     */
    private function bindComponents(): void {
        foreach ($this->components ?? [] as $handle => $class) {
            Livewire::component($handle, $class);
        }
    }

    /**
     * Binds discovered Blade views.
     */
    private function bindViews(): void {
        foreach ($this->views ?? [] as $handle => $path) {
            View::addNamespace($handle, dirname($path));
        }
    }
}
