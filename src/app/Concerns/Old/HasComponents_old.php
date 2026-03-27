<?php

namespace MM\Meros\App\Services\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

use Livewire\Component;
use Livewire\Livewire;

use MM\Meros\App\Support\ClassInfo;

trait HasComponents {
    /**
     * Indicates whether the item has components.
     * Note: by component we mean a Livewire component.
     * 
     * @var boolean
     */
    private bool $hasComponents = false;

    /**
     * Indicates whether the item has views.
     * 
     * @var boolean
     */
    private bool $hasViews = false;
    
    /**
     * The components directory relative to the feature
     * directory.
     * 
     * @var string
     */
    protected string $componentsDir = 'components';

    /**
     * The views directory relative to the feature
     * directory.
     * 
     * @var string
     */
    protected string $viewsDir = 'views';

    /**
     * Discovered components.
     * 
     * @var array
     */
    protected array $components = [];

    /**
     * Discovered views.
     * 
     * @var array
     */
    protected array $views = [];

    /**
     * Sets absolute path and calls setComponents.
     * 
     * @return void
     */
    protected function discoverComponents(): void {
        $componentsPath = $this->path . $this->componentsDir;
        $this->findComponents($componentsPath);
    }

    /**
     * Sets absolute path and calls setViews.
     * 
     * @return void
     */
    protected function discoverViews(): void {
        $viewsPath = $this->path . $this->viewsDir;
        $this->findViews($viewsPath);
    }

    /**
     * Uses glob to search the given path for valid components.
     * Components will be indentified as a php file that contains
     * a class extending Livewire\\Component.
     *
     * @param string $path
     * @return void
     */
    private function findComponents(string $path): void {
        if (! File::exists($path)) {
            return;
        }

        $candidates = File::glob($path . '/*.php');

        foreach ($candidates as $component) {

            $class = ClassInfo::getFromPath($component);

            if ($class->extends(Component::class)) {
                $handle = $this->slug . '.' . Str::lower(Str::replace('.php', '', basename($component)));
                $this->addComponent($handle, $class);
            }
        }

        $this->hasComponents = $this->components !== [];
    }

    /**
     * Uses glob to search the given path for blade files and adds them as views.
     *
     * @param string $path
     * @return void
     */
    private function findViews(string $path): void {
        if (! File::exists($path)) {
            return;
        }

        $candidates = File::glob($path . '/*.blade.php');

        foreach ($candidates as $view) {
            $this->addView($this->slug, $view);
        }

        $this->hasViews = $this->views !== [];
    }

    /**
     * Registers a Livewire component.
     *
     * @param string $handle
     * @param object $class
     * @return void
     */
    protected function addComponent(string $handle, object $class): void {
        $this->components[$handle] = $class->name;
    }

    /**
     * Registers a Blade view.
     * 
     * @param string $handle
     * @param string $path
     * @return void
     */
    protected function addView(string $handle, string $path): void {
        $this->views[$handle] = $path;
    }

    /**
     * Binds discovered Livewire components.
     * 
     * @return void
     */
    private function enqueueComponents(): void {
        foreach ($this->components ?? [] as $handle => $class) {
            Livewire::component($handle, $class);
        }
    }

    /**
     * Binds discovered Blade views.
     * 
     * @return void
     */
    private function enqueueViews(): void {
        foreach ($this->views ?? [] as $handle => $path) {
            View::addNamespace($handle, dirname($path));
        }
    }
}
