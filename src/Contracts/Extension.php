<?php 

namespace MM\Meros\Contracts;

/**
 * Extension packages should extend this class and define
 * the configure() method provided by the Feature contract.
 * 
 * The override method should be defined by the theme's override
 * file for the extension. This is found in app/Extensions by default.
 */
abstract class Extension extends Feature
{
    /**
     * This is called after the extension's configure() method
     * allowing theme developers to override any configurations
     * set by the extension via the Feature contract's configure()
     * method.
     *
     * @return void
     */
    protected abstract function override(): void;
}