<?php 

namespace MM\Meros\App\Providers;

use MM\Meros\Contracts\MerosServiceProvider;

use MM\Meros\App\Package;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Packages;

use MM\Meros\Support\ClassInfo;

abstract class PackageServiceProvider extends MerosServiceProvider {
    /**
     * The fully qualified class name of the class that extends the 
     * Package class and is being registered by this service provider.
     *
     * @var string
     */
    protected string $packageClass;

    /**
     * Indicates whether the package class has been set.
     *
     * @var bool
     */
    private bool $packageClassSet = false;

    /**
     * The instance of the package being registered.
     *
     * @var Package
     */
    private Package $instance;

    /**
     * Indicates whether the package is enabled.
     *
     * @var bool
     */
    private bool $enabled = false;

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Makes the config files in a directory publishable to the theme's
     * config directory with `wp meros:publish --provider=<this provider>`.
     *
     * @param string $directory Directory containing `name.php` config files.
     * @param string $prefix    Optional prefix for published filenames.
     *
     * @return void
     */
    final protected function publishesConfig(string $directory, string $prefix = ''): void {
        $paths = [];

        foreach (glob(rtrim($directory, '/\\') . '/*.php') ?: [] as $file) {
            $paths[$file] = config_path($prefix . basename($file));
        }

        $this->publishes($paths, 'config');
    }

    /**
     * Merges a package config file beneath the active configuration.
     *
     * @param string $file
     * @param string $key
     * @param string|null $publishedFile
     *
     * @return void
     */
    final protected function mergePackageConfig(string $file, string $key, ?string $publishedFile = null): void {
        $defaults = require $file;

        if (!is_array($defaults)) {
            throw new \UnexpectedValueException("Configuration '{$key}' must be an array.");
        }

        if ($publishedFile !== null) {
            $publishedPath = config_path($publishedFile);
            if (is_file($publishedPath)) {
                $published = require $publishedPath;
                if (!is_array($published)) {
                    throw new \UnexpectedValueException("Published configuration '{$publishedFile}' must be an array.");
                }

                $defaults = $this->mergeConfigArrays($defaults, $published);
            }
        }

        $overrides = config($key, []);
        if (!is_array($overrides)) {
            throw new \UnexpectedValueException("Configuration '{$key}' must be an array.");
        }

        config([$key => $this->mergeConfigArrays($defaults, $overrides)]);
    }

    /**
     * Recursively merges associative configuration maps while replacing lists.
     */
    private function mergeConfigArrays(array $defaults, array $overrides): array {
        foreach ($overrides as $key => $value) {
            if (
                isset($defaults[$key])
                && is_array($defaults[$key])
                && is_array($value)
                && !array_is_list($defaults[$key])
                && !array_is_list($value)
            ) {
                $defaults[$key] = $this->mergeConfigArrays($defaults[$key], $value);
            } else {
                $defaults[$key] = $value;
            }
        }

        return $defaults;
    }

    /**
     * Initialises the service provider. 
     * 
     * Should be overridden by child classes to set the package class 
     * using the setPackageClass() method if not set directly in the property declaration.
     *
     * @return void
     */
    protected function init(): void {}

    /**
     * Sets the package class for this service provider.
     *
     * @param string $class
     *
     * @return void
     * @throws \InvalidArgumentException if the provided class does not extend the Package class.
     */
    final protected function setPackageClass(string $class): void {
        $info = ClassInfo::get($class);

        if ($info->extends(Package::class)) {
            $this->packageClass = $class;
            $this->packageClassSet = true;
        } else {
            throw new \InvalidArgumentException("The class {$class} must extend the Package class.");
        }
    }

    /**
     * Registers the package's services.
     *
     * @return void
     */
    final public function register(): void {
        $this->init();

        if (!$this->packageClassSet && empty($this->packageClass)) {
            throw new \RuntimeException("The package class must be set using setPackageClass() before calling register().");
        }

        if (!$this->packageClassSet && !empty($this->packageClass)) {
            $this->setPackageClass($this->packageClass);
        }

        // Instantiate the package to trigger the constructor and set up the package
        $this->instance = $this->app->make($this->packageClass)->get();

        // Set whether the package is enabled
        $settingName     = $this->instance->getHandle() . '_enabled';
        $packageSettings = Framework::__getPackageSettings();
        $this->enabled   = (bool) ($packageSettings[$settingName] ?? false);

        // Add the package to the Packages register
        Packages::register($this->instance);
    }

    // =========================================================================
    // Booting
    // =========================================================================

    /**
     * Boots the package's services if it is enabled.
     *
     * @return void
     */
    final public function boot(): void {
        if ($this->enabled) {
            // Run the beforeBoot method
            $this->beforeBoot();

            // Initialise the package
            $package = $this->instance;

            // Register Livewire components
            $this->registerLivewireComponents($package);
            
            // Load views from the packages's views directory
            $this->registerViews($package);

            // Load routes from the package's routes directory
            $this->registerRoutes($package);

            // Call the package's configure method.
            $package->configure();

            // Run the afterBoot method
            $this->afterBoot();
        }
    }

    /**
     * Method to be overridden by child classes to perform actions before the boot process.
     *
     * @return void
     */
    protected function beforeBoot(): void {
        // This method can be overridden by child classes to perform actions before the boot process
    }

    /**
     * Method to be overridden by child classes to perform actions after the boot process.
     *
     * @return void
     */
    protected function afterBoot(): void {
        // This method can be overridden by child classes to perform actions after the boot process
    }
}