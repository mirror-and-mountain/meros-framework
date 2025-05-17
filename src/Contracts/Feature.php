<?php 

namespace MM\Meros\Contracts;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Traits\AssetManager;
use MM\Meros\Traits\BlockManager;
use MM\Meros\Traits\IncludeManager;
use MM\Meros\Traits\ComponentManager;
use MM\Meros\Traits\SettingsManager;

/**
 * Features should extend this class and define
 * the configure method().
 */
abstract class Feature
{
    /**
     * The type of feature. This is set automatically
     * based on the type of child class used.
     *
     * @var string
     */
    public string $type = 'feature';

    /**
     * Whether the feature is enabled.
     * Determines whether actions are taken in the initialise() method.
     *
     * @var bool
     */
    public bool $enabled = true;

    /**
     * Determines whether an 'enabled' option is available to
     * users via the WP dashboard.
     *
     * @var bool
     */
    public bool $userSwitchable = true;

    /**
     * Whether the feature's initialised() method has been called.
     *
     * @var bool
     */
    public bool $initialised = false;

    /**
     * The name of the feature in slug_format. Used in various
     * filter hooks.
     *
     * @var string
     */
    protected string $name;

    /**
     * The feature's name including author_name_feature_name.
     *
     * @var string
     */
    protected string $fullName;

    /**
     * The feature's path.
     *
     * @var string
     */
    protected string $path;

    /**
     * The feature's URI.
     *
     * @var string
     */
    protected string $uri;

    /**
     * The feature's category. This deterines which area of the 
     * THeme's settings page the feature's options appear in if
     * configured.
     *
     * @var string
     */
    protected string $category = 'miscellaneous';

    /**
     * The feature's author. May be initialised as a string or
     * an array with additional information.
     *
     * @var string|array
     */
    protected string|array $author = 'unknown';

    use AssetManager, 
        BlockManager, 
        IncludeManager, 
        ComponentManager, 
        SettingsManager;

    public function __construct( string $path, string $uri, array $pluginInfo = [] )
    {
        $class = Str::afterLast( get_class($this), '\\' );
        $class = Str::lower( Str::headline( $class ) );
        
        // Set the feature's name
        $this->name = Str::slug( $class, '_' );
        // Set the feature's path
        $this->path = trailingslashit( $path );
        // Set the feature's URI
        $this->uri  = trailingslashit( $uri );

        // If the feature is a plugin, set the pluginInfo property
        if ( $this instanceof Plugin ) {
            $this->pluginInfo = $pluginInfo;
        }

        $this->setUp();
        $this->initialiseSettings();
    }

    /**
     * Sanitizes and sets various properties based on the type of 
     * feature being instantiated.
     *
     * @return void
     */
    private function setUp(): void
    {
        // Sets author info based on the properties provided by the main plugin file, if available.
        if ( $this instanceof Plugin ) {
            $this->type = 'plugin';
            
            $this->author = [
                'name'        => isset( $this->pluginInfo['Author'] ) ? $this->pluginInfo['Author'] : 'unknown',
                'description' => '',
                'support'     => '',
                'link'        => isset( $this->pluginInfo['Author URI'] ) ? $this->pluginInfo['Author URI'] : '',
            ];
        }

        $this->configure();

        if ( $this instanceof Extension ) {
            $this->type = 'extension';
            $this->override();
        }

        $this->sanitizeAuthor();

        $this->fullName = Str::slug( $this->author['name'], '_' ) . '_' . $this->name;
    }

    /**
     * Sanitizes the author property with the allowed keys.
     *
     * @return void
     */
    private function sanitizeAuthor(): void
    {
        if ( is_string( $this->author ) ) {
            $this->author = [
                'name'        => $this->author,
                'description' => '',
                'support'     => '',
                'link'        => ''
            ];
        } else if ( is_array( $this->author ) ) {
            $author = [
                'name'        => $this->author['name'] ?? 'unknown',
                'description' => $this->author['description'] ?? '',
                'support'     => $this->author['support'] ?? '',
                'link'        => $this->author['link'] ?? ''
            ];
            
            $this->author = $author;
        }
    }

    /**
     * This method should be defined in the feature's main class
     * found in app/Features/<Feature> by default.
     * 
     * Where a feature is a plugin the Plugin contract extends
     * this class and this method should be defined in the plugin's
     * configuration class found in app/Plugins by default.
     * 
     * Where a feature is an extension, the extension package should
     * extend the Extension contract and define this method. Users
     * can then override any configuration using the Extension
     * contract's override() method which is called after configure().
     *
     * @return void
     */
    abstract protected function configure(): void;

    /**
     * Prepares and hooks the feature's declared supports into
     * the Wordpress lifecycle.
     *
     * @return void
     */
    final public function initialise(): void
    {
        // Stop if the feature isn't enabled
        if ( $this->enabled === false ) {
            return;
        }

        // Stop if the feature has been disabled in the WP dashboard
        if ( $this->userSwitchable === true &&
             $this->settings['enabled'] === '0' 
        ) {
            $this->enabled = false;
            return;
        }

        // If the feature is a plugin, check the main plugin file exists and include it
        if (
            $this instanceof Plugin &&
            File::exists( base_path( $this->pluginInfo['File'] ) ) 
        ) {
            include_once base_path( $this->pluginInfo['File'] );
            $this->initialised = true;
            return;
        }

        if ($this->hasIncludes) {
            $this->loadIncludes();
            $this->include();
        }

        if ($this->hasAssets) {
            $this->loadAssets();
            $this->enqueueAssets();
        }

        if ($this->hasComponents) {
            $this->loadComponents();
        }

        if ($this->hasViews) {
            $this->loadViews();
        }

        if ($this->hasBlocks) {
            $this->loadBlocks();
            $this->registerBlocks();
        }

        $this->initialised = true;
    }

    /**
     * Returns the feature's author info.
     *
     * @return array
     */
    final public function getAuthor(): array
    {
        return $this->author;
    }

    /**
     * Returns either the feature's name or fullname if requested.
     *
     * @param  bool   $full
     * @return string
     */
    final public function getName( bool $full = false ): string
    {
        return $full === false ? $this->name : $this->fullName;
    }
}