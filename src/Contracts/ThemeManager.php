<?php 

namespace MM\Meros\Contracts;

use Roots\Acorn\Application as RootsApplication;
use MM\Meros\Providers\MerosServiceProvider;

use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Helpers\Features;
use MM\Meros\Traits\ContextManager;
use MM\Meros\Traits\AuthorManager;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Foundation\Application;

abstract class ThemeManager implements ThemeInterface
{
    protected array $categories = [];
    protected array $features   = [];

    protected bool  $disableThemeSettings = false;
    protected bool  $alwaysInjectLivewire = false;
    public bool     $useSinglePageLoading = false;

    use ContextManager, AuthorManager;

    final public function __construct( protected Application $app )
    {
        $theme = wp_get_theme();
        $this->context     = $theme->get('Name');
        $this->contextUri  = get_theme_file_uri();

        $this->configure();

        if ( $this->categories === [] ) {
            $this->categories = [
                'blocks'        => 'meros_theme_settings',
                'miscellaneous' => 'meros_theme_settings'
            ];
        }

        if ( !$this->disableThemeSettings ) {
            add_action( 'admin_menu', [$this, 'initialiseAdminPages'] );
        }

        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_script( 
                'livewire', 
                get_theme_file_uri('vendor/livewire/livewire/dist/livewire.js'),
                [],
                null,
                true
            );
        });
    }

    final public static function bootstrap( string $themeName, array $providers = [] ): void
    {
        defined('MEROS_BOOT')     || define('MEROS_BOOT', true);
        defined('MEROS_BASEPATH') || define('MEROS_BASEPATH', get_theme_file_path());
        defined('MEROS_BASEURI')  || define('MEROS_BASEURI', get_theme_file_uri());

        if ( MEROS_BOOT !== false && class_exists( RootsApplication::class ) ) {

            add_action( 'after_setup_theme', function() use ( $providers ) {

                $providers = array_merge([MerosServiceProvider::class], $providers);

                RootsApplication::configure( MEROS_BASEPATH )
                    ->withProviders( $providers )
                    ->withRouting( wordpress: true )
                    ->boot();

            }, 0);
        }

        // Enqueue theme stylesheet
        add_action('wp_enqueue_scripts', function () use ( $themeName ) {
            $handle = Str::slug( $themeName, '-' . '-styles' );
            wp_enqueue_style(
                $handle, 
                get_stylesheet_uri()
            );
        });
    }

    protected abstract function configure(): void;

    final public function addFeature( 
        string $name, 
        string $category, 
        string|callable $bootstrapper, 
        string|array $author, 
        array $args = [] 
    ): bool
    {   
        if ( !in_array( $category, array_keys( $this->categories ) ) ) { return false; } // Return false if the category isn't valid
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
                'optionGroup' => $this->categories[ $category ],
                'path'        => $args['path'] ?? $classInfo->path,
                'uri'         => $args['uri'] ?? $classInfo->uri
            ];

            $pluginInfo = $classInfo->extends( Plugin::class ) ? $args['pluginInfo'] : null;
            $feature    = Features::instantiate( $this->app, $bootstrapper, $featureArgs, $pluginInfo  );

            Arr::set( $this->features, $dotName, $feature ); // Add the feature
        }

        return true;
    }

    final public function __addInstantiatedFeature( string $name, object $feature ): void
    {
        if ( !array_key_exists( $name, $this->features ) ) {
            Arr::set( $this->features, $name, $feature );
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

    final public function initialiseAdminPages(): void
    {
        // Theme Settings
        add_theme_page(
            "{$this->context} Settings",
            'Settings',
            'manage_options',
            'meros_theme_settings',
            function () {
                echo "<div class=\"wrap\"><h1>" . esc_html( $this->context ) . " Settings</h1><p>I am an options page.</p></div>";
            }            
        );
    }

    final public function initialise(): void
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