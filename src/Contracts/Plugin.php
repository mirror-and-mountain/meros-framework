<?php 

namespace MM\Meros\Contracts;

/**
 * Configuration files for installed plugins are found in
 * app/Plugins by default and should extend this class.
 * 
 * Theme developers should define the Feature contract's
 * configure() method in classes that extend this contract.
 */
abstract class Plugin extends Feature
{
    /**
     * Plugin information parsed from the plugin's main file.
     * Can be used to set the feature's author info property.
     *
     * @var array
     */
    protected array $pluginInfo;
}