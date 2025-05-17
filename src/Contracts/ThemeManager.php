<?php 

namespace MM\Meros\Contracts;

use Roots\Acorn\Application as RootsApplication;
use MM\Meros\Providers\MerosServiceProvider;

use MM\Meros\Helpers\Livewire;
use MM\Meros\Traits\ContextManager;
use MM\Meros\Traits\AuthorManager;
use MM\Meros\Traits\AdminManager;

use Illuminate\Support\Arr;
use Illuminate\Contracts\Foundation\Application;

/**
 * The theme's main class should extend this and define
 * the configure() method.
 */
abstract class ThemeManager
{
    /**
     * The theme's features.
     *
     * @var array
     */
    private array $features = [];

    /**
     * Determines whether Livewire scripts and styles
     * are always injected into WP's header & footer.
     *
     * @var bool
     */
    public bool $always_inject_livewire_assets = false;

    /**
     * Used by the Livewire helper to determine whether
     * Livewire assets have already been injected.
     *
     * @var bool
     */
    public bool $livewireInitialised = false;

    use ContextManager, AuthorManager, AdminManager;

    final public function __construct( protected Application $app )
    {
        $this->setContext();
        $this->setOptionsMap();
        $this->configure();
        $this->sanitizeOptionsMap();
    }

    /**
     * Bootstraps the theme's Laravel App using Acorn's Application class. 
     * Additional providers can be passed. 
     * 
     * This method should be called from the theme's functions.php file.
     *
     * @param  array $providers
     *
     * @return void
     */
    final public static function bootstrap( array $providers = [] ): void
    {
        if ( class_exists( RootsApplication::class ) ) {
            add_action( 'after_setup_theme', function() use ( $providers ) {
                $providers = array_merge([MerosServiceProvider::class], $providers);
                $root      = get_stylesheet_directory();
                RootsApplication::configure( $root )
                    ->withProviders( $providers )
                    ->withRouting( wordpress: true )
                    ->boot();

            }, 0);
        }

        // Clear out any existing session files when the theme is activated.
        add_action('after_switch_theme', function () {
            $sessionDir = get_theme_file_path('storage/framework/sessions');
        
            if ( !is_dir( $sessionDir ) ) {
                return;
            }
        
            $files = glob( $sessionDir . '/*' );
        
            foreach ( $files as $file ) {
                if ( is_file( $file ) ) {
                    unlink( $file );
                }
            }
        });
    }

    /**
     * This method should be defined in the theme's main class
     * found at app/Theme.php by default.
     * 
     * Can be used to change the values of various properties
     * before they are used.
     *
     * @return void
     */
    protected abstract function configure(): void;

    final public function addFeature( string $name, object $feature ): void
    {
        if ( !array_key_exists( $name, $this->features ) ) {
            Arr::set( $this->features, $name, $feature );
        }
    }

    /**
     * This is called after theme's features have been instantiated 
     * and added to the features property in the boot method of the 
     * Meros Service Provider.
     * 
     * @see ../Providers/MerosServiceProvider
     * @return void
     */
    final public function initialise(): void
    {
        $this->initialiseAdmin();
        $this->initialiseAssets();
        $this->initialiseFeatures();
    }

    /**
     * Injects Livewire Assets via the Livewire helper if required.
     * Additionally, the theme's stylesheet is enqueued here.
     *
     * @return void
     */
    private function initialiseAssets(): void
    {
        if ( $this->always_inject_livewire_assets && !is_admin() ) {
            Livewire::injectAssets();
        }

        $this->enqueueThemeStyle();

        /**
         * Additional assets preparation can be done here in the future.
         * E.g. Vite injection.
         */
    }

    /**
     * Enqueues the theme's stylesheet.
     *
     * @return void
     */
    private function enqueueThemeStyle(): void
    {
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
     * Calls the initialise method on each of the theme's features.
     * This ultimately hooks any registered features into Wordpress.
     *
     * @return void
     */
    private function initialiseFeatures():void
    {
        $features = Arr::dot( $this->features );

        foreach ( $features as $feature ) {
            $feature->initialise();
        }
    }

    /**
     * Returns the features array.
     *
     * @return array
     */
    final public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * Returns a particular feature from the features array.
     *
     * @param  string      $name
     * @return object|null
     */
    final public function getFeature( string $name ): object|null
    {
        return Arr::get( $this->features, $name ) ?? null;
    }
}