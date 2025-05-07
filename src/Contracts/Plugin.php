<?php 

namespace MM\Meros\Contracts;

use Illuminate\Support\Facades\File;

abstract class Plugin extends Feature
{
    protected array  $pluginInfo;
    protected string $pluginFile;
    protected bool   $usePluginConfig;

    public function setPluginInfo( array $pluginInfo ): void
    {
        $this->pluginInfo = $pluginInfo;
        $this->pluginFile = base_path($pluginInfo['File']);
    }

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
            $this->usePluginConfig && 
            File::exists( $this->pluginFile ) 
        ) {
            include_once $this->pluginFile;
            $this->initialised = true;
            return;
        }

        parent::initialise();
    }
}