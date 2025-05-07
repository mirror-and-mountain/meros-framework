<?php 

namespace MM\Meros\Contracts;

use Roots\Acorn\Application as RootsApplication;
use MM\Meros\Providers\MerosServiceProvider;

use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\Features;
use MM\Meros\Helpers\Livewire;
use MM\Meros\Traits\ContextManager;
use MM\Meros\Traits\AuthorManager;
use MM\Meros\Traits\AdminManager;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Foundation\Application;

abstract class ThemeManager implements ThemeInterface
{
    protected array $featureCategories = [];
    private   array $features          = [];

    protected bool  $enable_smooth_scrolling       = false;
    public    bool  $always_inject_livewire_assets = false;

    public    bool  $livewireInitialised = false;

    use ContextManager, AuthorManager, AdminManager;

    final public function __construct( protected Application $app )
    {
        $this->setContext();
        $this->configure();
        $this->enqueueThemeStyle();
        $this->setFeatureCategories();
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
    }

    private function setFeatureCategories(): void
    {
        if ( $this->featureCategories === [] ) {
            $this->featureCategories = [
                'blocks'        => 'meros_theme_settings',
                'miscellaneous' => 'meros_theme_settings'
            ];
        }
    }

    protected abstract function configure(): void;

    private function enqueueThemeStyle(): void
    {
        $themeName = $this->themeName;
        add_action('wp_enqueue_scripts', function () use ( $themeName ) {
            $handle = Str::slug( $themeName, '-' . '-styles' );
            wp_enqueue_style(
                $handle, 
                get_stylesheet_uri(),
                [],
                filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css')
            );

            if ( $this->enable_smooth_scrolling ) {
                $scrollingCSS = "
                    html {
                        scroll-behavior: smooth;
                    }
                ";

                wp_add_inline_style( $handle, $scrollingCSS );
            }
        });
    }

    final public function addFeature( string $name, string $category, string|callable $bootstrapper, string|array $author, array $args = [] ): bool
    {   
        if ( !in_array( $category, array_keys( $this->featureCategories ) ) ) { return false; } // Return false if the category isn't valid
        $author = $this->addAuthor( $author ); // Sanitize and add the author. Returns formatted name on success
        if ( !$author ) { return false; } // Return false if the author isn't valid

        $name     = Str::slug( $name, '_' ); // Sanitize the feature name
        $dotName  = $author . '.' . $name; // author.feature dot notation for features array
        $fullName = $author . '_' . $name; // author_feature format for settings        

        if ( array_key_exists( $dotName, $this->features ) ) { return false; } // Return false if the feature already exists

        if ( is_callable( $bootstrapper ) ) {
            Arr::set( $this->features, $dotName, $bootstrapper );
        } else {
            $classInfo = ClassInfo::get( $bootstrapper ); // Get the feature class
            if ( ! $classInfo->isDescendantOf( Feature::class ) ) { return false; } // Return false if the feature isn't compliant

            $featureArgs = [
                'name'        => $name,
                'fullName'    => $fullName,
                'dotName'     => $dotName,
                'category'    => $category,
                'optionGroup' => $this->featureCategories[ $category ],
                'path'        => $args['path'] ?? $classInfo->path,
                'uri'         => $args['uri'] ?? $classInfo->uri
            ];

            $pluginInfo = $classInfo->extends( Plugin::class ) ? $args['pluginInfo'] : null;
            $feature    = Features::instantiate( $this->app, $bootstrapper, $featureArgs, $pluginInfo  );

            Arr::set( $this->features, $dotName, $feature ); // Add the feature
        }

        return true;
    }

    final public function __addInstantiatedFeature( string $name, object $feature, string|array $author ): void
    {
        $author = $this->addAuthor( $author );
        if ( !$author ) { return; }
        
        $dotName = $author . '.' . $name;

        if ( !array_key_exists( $dotName, $this->features ) ) {
            Arr::set( $this->features, $dotName, $feature );
        }
    }

    final public function getFeatures(): array
    {
        return $this->features;
    }

    final public function getFeature( string $name ): string|object|null
    {
        return Arr::get( $this->features, $name ) ?? null;
    }

    final public function initialise(): void
    {
        $this->initialiseAdmin();
        $this->initialiseAssets();
        $this->initialiseFeatures();
    }

    private function initialiseAssets(): void
    {
        if ( 
            $this->always_inject_livewire_assets && 
            is_admin() 
        ) 
        {
            Livewire::injectAssets();
        }
    }

    private function initialiseFeatures():void
    {
        $features = Arr::dot( $this->features );

        foreach ( $features as $feature ) {

            if ( is_callable( $feature ) ) {
                call_user_func( $feature );
            } 

            else {
                $feature->initialise();
            }

        }
    }
}