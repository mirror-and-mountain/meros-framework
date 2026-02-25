<?php

namespace MM\Meros\Contracts;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;

use MM\Meros\Helpers\Livewire;
use MM\Meros\Providers\MerosServiceProvider;

use MM\Meros\Traits\AdminManager;
use MM\Meros\Traits\AuthorManager;
use MM\Meros\Traits\ContextManager;
use MM\Meros\Traits\PermalinkManager;

use Roots\Acorn\Application as RootsApplication;

/**
 * The theme's main class should extend this and define
 * the configure() method.
 */
abstract class ThemeManager {
    /**
     * The theme's registered features.
     *
     * @var array
     */
    private array $features = [];

    /**
     * Indicated whether Livewire has been initialised 
     * on the frontend.
     *
     * @var bool
     */
    private bool $livewireInitialised = false;

    /**
     * Indicated whether Livewire has been initialised
     * in WP Admin.
     *
     * @var bool
     */
    private bool $livewireInitialisedAdmin = false;

    use AdminManager, AuthorManager, ContextManager, PermalinkManager;

    final public function __construct(protected Application $app) {
        $this->setContext();
        $this->setOptionsPages();
        $this->configure();
    }

    /**
     * Bootstraps the theme's Laravel App using Acorn's Application class.
     * Additional providers can be passed. Registers theme activation and switch hooks.
     *
     * This method should be called from the theme's functions.php file.
     * 
     * @param array $providers
     * @param string $authorName
     * @param string $authorDesc
     * @param string $authorUrl
     * @param string $authorSupportUrl
     * @return void
     */
    final public static function bootstrap(
        array $providers = [],
        string $authorName = 'Unknown',
        string $authorDesc = '',
        string $authorUrl = '',
        string $authorSupportUrl = ''
    ): void {
        if (class_exists(RootsApplication::class)) {
            self::$authorName = $authorName;
            self::$authorDesc = $authorDesc;
            self::$authorUrl = $authorUrl;
            self::$authorSupportUrl = $authorSupportUrl;

            add_action('after_setup_theme', function () use ($providers) {
                $providers = array_merge([MerosServiceProvider::class], $providers);
                $root = get_stylesheet_directory();
                RootsApplication::configure($root)
                    ->withProviders($providers)
                    ->withRouting(wordpress: true)
                    ->boot();
            }, 0);
        }

        // Hook for when theme is activated.
        add_action('after_switch_theme', function () {
            // Clear session files.
            $sessionDir = get_theme_file_path('storage/framework/sessions');

            if (is_dir($sessionDir)) {
                $files = glob($sessionDir . '/*');

                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }

            // Ensure an APP_KEY exists for Livewire.
            Livewire::ensureAppKey();

            // Ensure pretty permalinks are set.
            self::ensurePrettyPermalinks();
        });

        // Hook for when theme is switched.
        add_action('switch_theme', function () {
            // Unregister theme settings.
            $settings = self::$registeredSettings;
            foreach ($settings as $_ => $optionGroups) {
                foreach ($optionGroups as $optionGroup => $options) {
                    foreach ($options as $optionName => $_) {
                        unregister_setting($optionGroup, $optionName);
                        delete_option($optionName);
                    }
                }
            }
        });

        // Hook for when theme is uninstalled.
    }

    /**
     * This method should be defined in the theme's main class
     * found at app/Theme.php by default.
     *
     * Can be used for things like adding theme supports.
     * 
     * @return void
     */
    abstract protected function configure(): void;

    /**
     * Adds a feature to the theme's features array.
     *
     * @param string $name
     * @param object $feature
     * @return void
     */
    final public function addFeature(string $name, object $feature): void {
        if (! array_key_exists($name, $this->features)) {
            Arr::set($this->features, $name, $feature);
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
     * This is called after theme's features have been instantiated
     * and added to the features property in the boot method of the
     * Meros Service Provider.
     *
     * @see ../Providers/MerosServiceProvider
     * @return void
     */
    final public function initialise(): void {
        $this->initialiseAdmin();
        $this->initialiseAssets();
        $this->hookFeatures();
        $this->afterHookFeatures();
    }

    /**
     * Initialises theme assets such as stylesheets and scripts.
     * 
     * @return void
     */
    private function initialiseAssets(): void {
        $this->enqueueThemeStyle();
    }

    /**
     * Enqueues the theme's stylesheet.
     * 
     * @return void
     */
    private function enqueueThemeStyle(): void {
        add_action('wp_enqueue_scripts', function () {
            $handle = $this->themeSlug . '_style'; // e.g. meros_style.
            wp_enqueue_style(
                $handle,
                get_stylesheet_uri(),
                [],
                filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css')
            );
        });
    }

    /**
     * Handles Livewire assets injection.
     * Called by features when required.
     * 
     * @param bool $admin Whether to initialise for the admin area.
     * @return void
     */
    final public function initialiseLivewire(bool $admin = false): void {
        if ($admin && $this->livewireInitialisedAdmin === false) {
            $this->livewireInitialisedAdmin = Livewire::injectAssets(true);
        } elseif (! $admin && $this->livewireInitialised === false) {
            $this->livewireInitialised = Livewire::injectAssets(false);
        }
    }

    /**
     * Calls the hook method on each of the theme's features.
     * This ultimately hooks any registered features into Wordpress.
     * 
     * @return void
     */
    private function hookFeatures(): void {
        $features = Arr::dot($this->features);
        foreach ($features as $feature) {
            $feature->hook();
            $featureName = $feature->getName(true);
            $featureSettings = $feature->getSettings();
            self::$registeredSettings[$featureName] = $featureSettings;
        }
    }

    /**
     * Calls the runAfterHook method on each of the theme's features.
     * Allows features to perform tasks after all of the theme's features
     * have been hooked.
     * 
     * @return void
     */
    private function afterHookFeatures(): void {
        $features = Arr::dot($this->features);

        foreach ($features as $feature) {
            $feature->runAfterHook();
        }
    }

    /**
     * Returns the features array.
     * 
     * @return array
     */
    final public function getFeatures(): array {
        return $this->features;
    }

    /**
     * Returns a particular feature from the features array.
     * 
     * @param string $name The name of the feature.
     * @return object|null
     */
    final public function getFeature(string $name): ?object {
        return Arr::get($this->features, $name) ?? null;
    }

    /**
    * Returns the theme's stylesheet handle.
    *
    * @return string
    */
    final public function getThemeStyleSheetHandle(): string {
        return $this->themeSlug . '_style';
    }

    /**
     * Returns whether Livewire assets have been initialised.
     * 
     * @param bool $admin Whether to check for admin area.
     * @return bool
     */
    final public function livewireInitialised(bool $admin = false): bool {
        return $admin ? $this->livewireInitialisedAdmin : $this->livewireInitialised;
    }
}
