<?php

namespace MM\Meros\Helpers;

class Features
{
    /**
     * A helper to instantiate theme features before they are
     * bound to the theme manager.
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
            fn () => new $class($theme, $name, $authorInfo, $path, $uri)
        );

        return app()->make($class);
    }
}
