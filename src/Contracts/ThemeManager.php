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

abstract class ThemeManager
{
    private array $features    = [];

    public bool $always_inject_livewire_assets = false;
    public bool $livewireInitialised = false;

    use ContextManager, AuthorManager, AdminManager;

    final public function __construct( protected Application $app )
    {
        $this->setContext();
        $this->setOptionsMap();
        $this->configure();
        $this->sanitizeOptionsMap();
    }

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

    protected abstract function configure(): void;

    final public function addFeature( string $name, object $feature ): void
    {
        if ( !array_key_exists( $name, $this->features ) ) {
            Arr::set( $this->features, $name, $feature );
        }
    }

    final public function initialise(): void
    {
        $this->initialiseAdmin();
        $this->initialiseAssets();
        $this->initialiseFeatures();
    }

    private function initialiseAssets(): void
    {
        if ( $this->always_inject_livewire_assets && !is_admin() ) {
            Livewire::injectAssets();
        }

        $this->enqueueThemeStyle();
    }

    private function enqueueThemeStyle(): void
    {
        add_action('wp_enqueue_scripts', function () {
            $handle = $this->themeSlug . '_style';
            wp_enqueue_style(
                $handle, 
                get_stylesheet_uri(),
                [],
                filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css')
            );
        });
    }

    private function initialiseFeatures():void
    {
        $features = Arr::dot( $this->features );

        foreach ( $features as $feature ) {
            $feature->initialise();
        }
    }

    final public function getFeatures(): array
    {
        return $this->features;
    }

    final public function getFeature( string $name ): object|null
    {
        return Arr::get( $this->features, $name ) ?? null;
    }
}