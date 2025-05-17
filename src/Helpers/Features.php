<?php 

namespace MM\Meros\Helpers;

class Features
{
    /**
     * A helper to instantiate theme features before they are
     * bound to the theme manager.
     *
     * @param  string $class
     * @param  string $path
     * @param  string $uri
     * @param  array  $pluginInfo
     * @return object
     */
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