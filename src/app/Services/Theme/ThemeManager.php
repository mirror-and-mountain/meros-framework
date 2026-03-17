<?php

namespace MM\Meros\App\Services\Theme;

use Illuminate\Support\Arr;

use MM\Meros\App\Services\Theme\Package;
use MM\Meros\App\Facades\Admin;

use MM\Meros\App\Services\Theme\Concerns\HasContext;
use MM\Meros\App\Services\Theme\Concerns\HasFeatures;

/**
 * The theme's main class should extend this and define
 * the configure() method.
 */
abstract class ThemeManager {
    /**
     * The theme's registered packages.
     *
     * @var array
     */
    private array $packages = [];

    /**
     * Whether to allow packages marked as experimental.
     *
     * @var boolean
     */
    protected bool $allowExperimentalPackages = true;

    /** 
     * Whether to allow migrations to be run from the admin area.
     * 
     * @var boolean
     */
    protected bool $allowMigrationsInAdmin = true;

    /**
     * Whether the theme's hookFeatures() method has been called.
     * 
     * @var bool
     */
    public bool $initialised = false;

    use HasContext, HasFeatures;

    final public function __construct() {
        // Set context
        $this->setContext();
    }

    /**
     * Binds a package to the theme's packages array.
     *
     * @param string $name
     * @param Package $package
     * @return void
     */
    final public function bindPackage(string $name, Package $package): void {
        if (! array_key_exists($name, $this->packages)) {
            Arr::set($this->packages, $name, $package);
        }
    }

    /**
     * Adds a Wordpress theme support.
     * 
     * @param string $support
     * @param mixed ...$args
     * @return void
     */
    protected function addThemeSupport(string $support, mixed ...$args): void {
        add_theme_support($support, $args);
    }

    /**
     * This is called after theme's packages have been bound to the theme.
     *
     * @return void
     */
    final public function initialise(): void {
        // Check context is set
        if (! $this->contextSet) {
            return;
        }

        $this->initialiseAssets();

        // Call child loaders
        $this->configure();
        $this->registerSettings();
        $this->registerInstallables();
        $this->loadFeatures();

        // Hook features into WordPress
        $this->hookFeatures();
        $this->runAfterHookFeatures();

        // Hook package features into WordPress
        $this->hookPackages();
        $this->afterHookPackages();
    }

    /**
     * Initialises theme assets such as stylesheets and scripts.
     * 
     * @return void
     */
    private function initialiseAssets(): void {
        add_action('wp_enqueue_scripts', function () {
            $handle = $this->slug . '_style'; // e.g. meros_style.
            wp_enqueue_style(
                $handle,
                get_stylesheet_uri(),
                [],
                filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css')
            );
        });
    }

    /**
     * Calls the hookFeatures method on each of the theme's packages.
     * 
     * @return void
     */
    private function hookPackages(): void {
        $packages = Arr::dot($this->packages);
        foreach ($packages as $package) {
            $package->hookFeatures();
            $packageName = $package->getName(true);
            $packageSettings = $package->getSettings();
            Admin::addRegisteredSettings($packageName, $packageSettings);
        }
    }

    /**
     * Calls the runAfterHookFeatures method on each of the theme's packages.
     * Allows packages to perform tasks after all of the theme's packages
     * have been hooked.
     * 
     * @return void
     */
    private function afterHookPackages(): void {
        $packages = Arr::dot($this->packages);

        foreach ($packages as $package) {
            $package->runAfterHookFeatures();
        }
    }

    /**
     * Returns the packages array.
     * 
     * @return array
     */
    final public function getPackages(): array {
        return $this->packages;
    }

    /**
     * Returns a particular package from the packages array.
     * 
     * @param string $name The name of the package.
     * @return object|null
     */
    final public function getPackage(string $name): ?object {
        return Arr::get($this->packages, $name) ?? null;
    }

    /**
    * Returns the theme's stylesheet handle.
    *
    * @return string
    */
    final public function getThemeStyleSheetHandle(): string {
        return $this->slug . '_style';
    }

    /**
     * Returns whether the theme allows experimental features.
     *
     * @return boolean
     */
    final public function allowsExperimentalPackages(): bool {
        return $this->allowExperimentalPackages;
    }

    /**
     * Returns the theme manager instance.
     * 
     * @return static
     */
    final public function getInstance(): static {
        return $this;
    }

    /**
     * Returns whether the theme allows migrations to be run from the admin area.
     * 
     * @return boolean
     */
    final public function allowsMigrationsInAdmin(): bool {
        return $this->allowMigrationsInAdmin;
    }
}
