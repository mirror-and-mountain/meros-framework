<?php 

namespace MM\Meros\Contracts;

use MM\Meros\Traits\AssetManager;
use MM\Meros\Traits\BlockManager;
use MM\Meros\Traits\IncludeManager;
use MM\Meros\Traits\ComponentManager;
use MM\Meros\Traits\SettingsManager;

abstract class Feature
{
    protected string $name;
    protected string $fullName;
    protected string $path;
    protected string $uri;
    protected string $category;
    protected bool   $isPlugin = false;
    protected array  $log = [];
    public    bool   $initialised = false;

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

        if ( 
            property_exists( $this, 'pluginInfo' ) &&
            property_exists( $this, 'pluginFile' ) 
        ) {
            $this->isPlugin = true;
        }

        $this->configure();
        $this->sanitizeOptions();
    }

    abstract protected function configure(): void;

    public function initialise(): void
    {
        // if (!$this->settings['enabled']) { 
        //     return; 
        // }

        if ($this->hasIncludes) {
            $this->include();
        }

        if ($this->hasAssets) {
            $this->enqueueAssets();
        }

        if ($this->hasBlocks) {
            $this->registerBlocks();
        }

        $this->initialised = true;
    }
}