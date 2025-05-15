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
        array  $pluginInfo = []
    ): object
    {
        $app->singleton(
            $class,
            fn() => new $class( $path, $uri, $pluginInfo )
        );

        return $app->make( $class );
    }
}