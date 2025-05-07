<?php 

namespace MM\Meros\Contracts;

use MM\Meros\Traits\AssetManager;
use MM\Meros\Traits\BlockManager;
use MM\Meros\Traits\IncludeManager;
use MM\Meros\Traits\ComponentManager;
use MM\Meros\Traits\SettingsManager;

abstract class Feature
{
    public    bool   $enabled        = true;
    public    bool   $userSwitchable = true;
    public    bool   $initialised    = false;
    protected string $name;
    protected string $fullName;
    protected string $path;
    protected string $uri;
    protected string $category;
    protected bool   $isExtension = false;
    protected bool   $isPlugin    = false;

    use AssetManager, 
        BlockManager, 
        IncludeManager, 
        ComponentManager, 
        SettingsManager;

    public function __construct( 
        string $name, 
        string $fullName, 
        string $category, 
        string $optionGroup,
        string $path, 
        string $uri 
    )
    {
        $this->name        = $name;
        $this->fullName    = $fullName;
        $this->category    = $category;
        $this->path        = trailingslashit( $path );
        $this->uri         = trailingslashit( $uri );
        $this->optionGroup = $optionGroup;

        $this->setUp();

        if ( is_admin() ) {
            $this->initialiseSettings();
        }
    }

    private function setUp(): void
    {
        if ( 
            property_exists( $this, 'pluginInfo' ) &&
            property_exists( $this, 'pluginFile' ) 
        ) {
            $this->isPlugin = true;
        }

        $this->configure();

        if ( $this instanceof Extension ) {
            $this->isExtension = true;
            $this->override();
        }
    }

    private function initialiseSettings(): void
    {
        $this->sanitizeOptions();
        $this->registerSettings();
    }

    abstract protected function configure(): void;

    public function initialise(): void
    {
        if ( 
            $this->enabled === false ||
            $this->userSwitchable === true &&
            $this->settings['enabled'] ?? false === false
        ) {
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
}