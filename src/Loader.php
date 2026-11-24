<?php 

namespace MM\Meros;

use MM\Foundations\Foundations;
use MM\Meros\App\Providers\FrameworkServiceProvider;

final class Loader {    
    /**
     * Boots the Laravel application through Meros Foundations.
     *
     * @param string[] $providers Additional service providers.
     * @param string[] $stubs     Extra config stub directories, applied after the framework's own.
     */
    public static function boot(
        array $providers = [],
        array $stubs     = []
    ): void {
        if (class_exists(Foundations::class)) {
            add_action('after_setup_theme', function () use ($providers, $stubs) {
                
                $merosProviders = [
                    FrameworkServiceProvider::class,
                ];
                
                Foundations::boot(
                    get_stylesheet_directory(),
                    array_merge($merosProviders, $providers),
                    $stubs
                );
            }, 0);
        }
    }
}
