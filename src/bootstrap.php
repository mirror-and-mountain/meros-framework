<?php

namespace MM\Meros\BootStrap;

use Roots\Acorn\Application;
use MM\Meros\Providers\MerosServiceProvider;

if ( ! function_exists( __NAMESPACE__ . '\\boot' ) && 
     ! function_exists( __NAMESPACE__ . '\\define_constants' ) ) {
    
    function define_constants(): void {
        defined('MEROS_BOOT')     || define('MEROS_BOOT', true);
        defined('MEROS_BASEPATH') || define('MEROS_BASEPATH', dirname(__FILE__, 2));
        defined('MEROS_BASEURI')  || define('MEROS_BASEURI', get_theme_file_uri());
    }

    function boot(): void {
        if ( MEROS_BOOT !== false && class_exists( Application::class ) ) {
            add_action('after_setup_theme', function () {
                $providers = apply_filters('meros_service_providers', [ MerosServiceProvider::class ]);
                Application::configure( MEROS_BASEPATH )
                    ->withProviders($providers)
                    ->withRouting(wordpress: true)
                    ->boot();
            }, 0);
        }
    }

    define_constants();
    boot();
}