<?php

namespace MM\Meros\Contracts;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;
use MM\Meros\Helpers\Livewire;
use MM\Meros\Providers\MerosServiceProvider;
use MM\Meros\Traits\AdminManager;
use MM\Meros\Traits\AuthorManager;
use MM\Meros\Traits\ContextManager;
use Roots\Acorn\Application as RootsApplication;

/**
 * The theme's main class should extend this and define
 * the configure() method.
 */
abstract class ThemeManager
{
    /**
     * The theme's features.
     */
    private array $features = [];

    /**
     * Used by the Livewire helper to determine whether
     * Livewire assets have already been injected.
     */
    private bool $livewireInitialised = false;

    /**
     * Used by the Livewire helper to determine whether
     * Livewire assets have already been injected in WP admin.
     */
    private bool $livewireInitialisedAdmin = false;

    use AdminManager, AuthorManager, ContextManager;

    final public function __construct(protected Application $app)
    {
        $this->setContext();
        $this->setOptionsPages();
        $this->configure();
    }

    /**
     * Bootstraps the theme's Laravel App using Acorn's Application class.
     * Additional providers can be passed.
     *
     * This method should be called from the theme's functions.php file.
     */
    final public static function bootstrap(
        array $providers = [],
        string $authorName = 'Unknown',
        string $authorDesc = '',
        string $authorUrl = '',
        string $authorSupportUrl = ''): void
    {
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

        // Clear out any existing session files when the theme is activated.
        add_action('after_switch_theme', function () {
            $sessionDir = get_theme_file_path('storage/framework/sessions');

            if (! is_dir($sessionDir)) {
                return;
            }

            $files = glob($sessionDir.'/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        });

        // Unregister all settings when the theme is switched.
        add_action('switch_theme', function () {
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

        add_filter('big_image_size_threshold', '__return_false');
    }

    /**
     * This method should be defined in the theme's main class
     * found at app/Theme.php by default.
     *
     * Can be used to change the values of various properties
     * before they are used.
     */
    abstract protected function configure(): void;

    final public function addFeature(string $name, object $feature): void
    {
        if (! array_key_exists($name, $this->features)) {
            Arr::set($this->features, $name, $feature);
        }
    }

    /**
     * Adds a Wordpress theme support.
     */
    protected function addThemeSupport(string $support, mixed ...$args): void
    {
        add_theme_support($support, $args);
    }

    /**
     * This is called after theme's features have been instantiated
     * and added to the features property in the boot method of the
     * Meros Service Provider.
     *
     * @see ../Providers/MerosServiceProvider
     */
    final public function initialise(): void
    {
        $this->initialiseAdmin();
        $this->initialiseAssets();
        $this->initialiseFeatures();
        $this->afterInitialiseFeatures();
    }

    /**
     * Injects Livewire Assets via the Livewire helper if required.
     * Additionally, the theme's stylesheet is enqueued here.
     */
    private function initialiseAssets(): void
    {
        $this->enqueueThemeStyle();
    }

    /**
     * Enqueues the theme's stylesheet.
     */
    private function enqueueThemeStyle(): void
    {
        add_action('wp_enqueue_scripts', function () {
            $handle = $this->themeSlug.'_style'; // e.g. meros_style.
            wp_enqueue_style(
                $handle,
                get_stylesheet_uri(),
                [],
                filemtime(trailingslashit(get_stylesheet_directory()).'style.css')
            );
        });
    }

    /**
     * Initialises Livewire assets injection.
     */
    final public function initialiseLivewire(bool $admin = false): void
    {
        if ($admin && $this->livewireInitialisedAdmin === false) {
            $this->livewireInitialisedAdmin = Livewire::injectAssets(true);
        } elseif (! $admin && $this->livewireInitialised === false) {
            $this->livewireInitialised = Livewire::injectAssets(false);
        }
    }

    /**
     * Calls the initialise method on each of the theme's features.
     * This ultimately hooks any registered features into Wordpress.
     */
    private function initialiseFeatures(): void
    {
        $features = Arr::dot($this->features);
        foreach ($features as $feature) {
            $feature->initialise();
            $featureName = $feature->getName(true);
            $featureSettings = $feature->getSettings();
            self::$registeredSettings[$featureName] = $featureSettings;
        }
    }

    /**
     * Calls the runAfterInitialise method on each of the theme's features.
     * Allows features to perform tasks after all of the theme's features
     * have been initialised.
     */
    private function afterInitialiseFeatures(): void
    {
        $features = Arr::dot($this->features);

        foreach ($features as $feature) {
            $feature->runAfterInitialise();
        }
    }

    /**
     * Returns the features array.
     */
    final public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * Returns a particular feature from the features array.
     */
    final public function getFeature(string $name): ?object
    {
        return Arr::get($this->features, $name) ?? null;
    }

    /*
    * Returns the theme's stylesheet handle.
    *
    * @return string
    */
    final public function getThemeStyleSheetHandle(): string
    {
        return $this->themeSlug.'_style';
    }

    /**
     * Returns whether Livewire assets have been initialised.
     */
    final public function livewireInitialised(bool $admin = false): bool
    {
        return $admin ? $this->livewireInitialisedAdmin : $this->livewireInitialised;
    }
}
