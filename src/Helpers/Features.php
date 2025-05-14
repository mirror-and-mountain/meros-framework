<?php 

namespace MM\Meros\Helpers;

use Illuminate\Contracts\Foundation\Application;

class Features
{
    public static function instantiate ( 
        Application $app, 
        string $class,
        string $path,
        string $uri,
        array|null $pluginInfo = null 
    ): object
    {
        $app->singleton(
            $class,
            fn() => new $class( $path, $uri )
        );

        $instance = $app->make( $class );

        if ( $pluginInfo ) {
            $instance->setPluginInfo( $pluginInfo );
        }

        return $instance;
    }
}