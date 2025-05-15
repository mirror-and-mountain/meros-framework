<?php 

namespace MM\Meros\Helpers;

class Features
{
    public static function instantiate ( 
        string $class,
        string $path,
        string $uri,
        array  $pluginInfo = []
    ): object
    {
        app()->singleton(
            $class,
            fn() => new $class( $path, $uri, $pluginInfo )
        );

        return app()->make( $class );
    }
}