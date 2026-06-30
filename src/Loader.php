<?php 

namespace MM\Meros;

use Roots\Acorn\Application as RootsApplication;
use MM\Meros\App\Providers\FrameworkServiceProvider;

final class Loader {    
    final public static function boot(
        array  $providers = []
    ): void {
        if (class_exists(RootsApplication::class)) {
            add_action('after_setup_theme', function () use ($providers) {
                
                $merosProviders = [
                    FrameworkServiceProvider::class,
                ];
                
                $providers = array_merge($merosProviders, $providers);
                $root      = get_stylesheet_directory();

                RootsApplication::configure($root)
                    ->withProviders($providers)
                    ->withRouting(wordpress: true)
                    ->boot();
            }, 0);
        }
    }
}