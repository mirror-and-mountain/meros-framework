<?php 

namespace MM\Meros\Contracts;

abstract class Plugin extends Feature
{
    protected array $pluginInfo;

    final public function setPluginInfo( array $pluginInfo ): void
    {
        $this->pluginInfo = $pluginInfo;
    }
}