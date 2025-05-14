<?php 

namespace MM\Meros\Contracts;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Traits\AssetManager;
use MM\Meros\Traits\BlockManager;
use MM\Meros\Traits\IncludeManager;
use MM\Meros\Traits\ComponentManager;
use MM\Meros\Traits\SettingsManager;

abstract class Feature
{
    public    string $type           = 'feature';
    public    bool   $enabled        = true;
    public    bool   $userSwitchable = true;
    public    bool   $initialised    = false;
    private   string $name;
    private   string $fullName;
    protected string $path;
    protected string $uri;
    protected string $category = 'miscellaneous';

    protected string|array $author = 'unknown';

    use AssetManager, 
        BlockManager, 
        IncludeManager, 
        ComponentManager, 
        SettingsManager;

    public function __construct( string $path, string $uri )
    {
        $class = Str::afterLast( __CLASS__, '\\' );
        $class = Str::lower( Str::headline( $class ) );
        
        $this->name = Str::slug( $class, '_' );
        $this->path = trailingslashit( $path );
        $this->uri  = trailingslashit( $uri );

        $this->setUp();
        $this->initialiseSettings();
    }

    private function setUp(): void
    {
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

        return;
    }

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

    private function initialiseSettings(): void
    {
        $this->sanitizeOptions();
        $this->setRegisteredSettings();
        $this->registerSettings();
    }

    abstract protected function configure(): void;

    public function initialise(): void
    {
        if ( $this->enabled === false ) {
            return;
        }

        if ( $this->userSwitchable === true &&
             $this->settings['enabled'] === '0' 
        ) {
            $this->enabled = false;
            return;
        }

        if (
            $this instanceof Plugin &&
            File::exists( $this->pluginInfo['File'] ) 
        ) {
            include_once $this->pluginInfo['File'];
            $this->initialised = true;
            return;
        }

        if ($this->hasIncludes) {
            $this->include();
        }

        if ($this->hasAssets) {
            $this->loadAssets();
            $this->enqueueAssets();
        }

        if ($this->hasComponents) {
            $this->loadComponents();
            $this->loadViews();
        }

        if ($this->hasBlocks) {
            $this->registerBlocks();
        }

        $this->initialised = true;
    }

    final public function getAuthor(): array
    {
        return $this->author;
    }

    final public function getName( bool $full = false ): string
    {
        return $full === false ? $this->name : $this->fullName;
    }
}