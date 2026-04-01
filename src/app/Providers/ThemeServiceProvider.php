<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

use MM\Meros\App\Theme as MerosTheme;
use MM\Meros\App\Support\ClassInfo;

class ThemeServiceProvider extends ServiceProvider {

    final public function register(): void {
        $themeClass = Config::get('theme.theme_class');
        $themeClass = ClassInfo::get($themeClass);

        if ($themeClass->extends(MerosTheme::class)) {
            $this->app->singleton(MerosTheme::class, function ($app) use ($themeClass) {
                return new $themeClass->name($app->make('meros.registry'));
            });
            
            $this->app->alias(MerosTheme::class, 'meros.theme');

            // Instantiate the theme to trigger the constructor and set up the theme
            $this->app->make(MerosTheme::class);
        }

        defined('MEROS') || define('MEROS', true);
    }

    final public function boot(): void {
        // Nothing to do...
    }
}
