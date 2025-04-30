<?php 

namespace MM\Meros\Helpers;

use Illuminate\Contracts\Foundation\Application;

class Features
{
    public static function instantiate ( 
        Application $app, 
        string $class, 
        array $args, 
        array|null $pluginInfo = null 
    ): object
    {
        $app->singleton(
            $args['dotName'],
            fn() => new $class( 
                $args['name'], 
                $args['fullName'], 
                $args['category'], 
                $args['optionGroup'], 
                $args['path'], 
                $args['uri'],
            )
        );

        $instance = $app->make( $args['dotName'] );

        if ( $pluginInfo ) {
            $instance->setPluginInfo( $pluginInfo );
        }

        return $instance;
    }
}