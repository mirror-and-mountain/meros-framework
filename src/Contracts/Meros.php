<?php 

namespace MM\Meros\Contracts;

use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Traits\ContextManager;
use MM\Meros\Traits\AuthorManager;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Contracts\Foundation\Application;

abstract class Meros implements FeatureManager 
{
    protected array $categories;
    protected array $features = [];

    use ContextManager, AuthorManager;

    public function __construct( protected Application $app )
    {
        $theme = wp_get_theme();
        $this->context = 'theme';
        $this->contextName = $theme->get('Name');
        $this->contextUri  = get_theme_file_uri();

        $this->categories = [
            'blocks'        => 'meros_theme_settings',
            'miscellaneous' => 'meros_theme_settings'
        ];

        add_action( 'admin_menu', [$this, 'initialiseAdminPages'] );
    }

    public function addFeature( string $name, string $category, string|callable $bootstrapper, string|array $author, array $args = [] ): bool
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
            $class = ClassInfo::get( $bootstrapper ); // Get the feature class
            if ( ! $class->isDescendantOf( Feature::class ) ) { return false; } // Return false if the feature isn't compliant

            $classArgs = [
                'name'     => $name,
                'fullName' => $fullName,
                'dotName'  => $dotName,
                'category' => $category,
                'path'     => $args['path'] ?? $class->path,
                'uri'      => $args['uri'] ?? $class->uri
            ];

            $pluginInfo = $class->extends( Plugin::class ) ? $args['pluginInfo'] : null;
            $feature    = $this->instantiateFeature( $class, $classArgs, $pluginInfo ); // Instantiate the featured

            Arr::set( $this->features, $dotName, $feature ); // Add the feature
        }

        return true;
    }

    protected function instantiateFeature( object $class, array $args, array|null $pluginInfo ): object
    {
        $optionGroup = $this->categories[ $args['category'] ];
        $this->app->singleton(
            $args['dotName'],
            fn() => new $class->name( 
                $args['name'], 
                $args['fullName'], 
                $args['category'], 
                $optionGroup, 
                $args['path'], 
                $args['uri'],
            )
        );

        $instance = $this->app->make( $args['dotName'] );

        if ( $pluginInfo ) {
            $instance->setPluginInfo( $pluginInfo );
        }

        return $instance;
    }

    public function getFeatures(): array
    {
        return $this->features;
    }

    public function getFeature( string $name ): string|object|null
    {
        return Arr::get( $this->features, $name ) ?? null;
    }

    public function initialiseAdminPages(): void
    {
        // Theme Settings
        add_theme_page(
            "{$this->contextName} Settings",
            'Settings',
            'manage_options',
            'meros_theme_settings',
            function () {
                echo "<div class=\"wrap\"><h1>" . esc_html( $this->contextName ) . " Settings</h1><p>I am an options page.</p></div>";
            }            
        );
    }

    public abstract function bootstrap();
}