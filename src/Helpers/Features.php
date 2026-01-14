<?php

namespace MM\Meros\Helpers;

class Features {
    /**
     * Instantiates theme features before they are
     * bound to the theme manager.
     * 
     * @param object $theme The theme instance.
     * @param string $class The fully qualified class name.
     * @param string $path The full path to the feature file.
     * @param string $uri The URI to the feature file.
     * @param array $authorInfo The feature author information.
     * @param string|null $name An optional name for the feature.
     * @return object The instantiated feature.
     */
    public static function instantiate(
        object $theme,
        string $class,
        string $path,
        string $uri,
        array $authorInfo,
        ?string $name = null,
    ): object {
        app()->singleton(
            $class,
            fn() => new $class($theme, $name, $authorInfo, $path, $uri)
        );

        return app()->make($class);
    }
}
