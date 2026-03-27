<?php 

namespace MM\Meros;

use Roots\Acorn\Application as RootsApplication;
use MM\Meros\App\Providers\FrameworkServiceProvider;

class Bootstrap {    
    final public static function bootstrap(
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