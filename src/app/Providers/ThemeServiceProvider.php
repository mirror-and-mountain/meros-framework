<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Theme as MerosTheme;
;
use MM\Meros\Support\ClassInfo;

use MM\Meros\Facades\Theme;

class ThemeServiceProvider extends ServiceProvider {

    final public function register(): void {
        $themeClass = Config::get('theme.theme_class');
        $themeClass = ClassInfo::get($themeClass);

        if ($themeClass->extends(MerosTheme::class)) {
            $this->app->singleton(MerosTheme::class, function () use ($themeClass) {
                return new $themeClass->name();
            });
            
            $this->app->alias(MerosTheme::class, 'meros.theme');

            // Instantiate the theme to trigger the constructor and set up the theme
            $this->app->make(MerosTheme::class);
        }

        defined('MEROS') || define('MEROS', true);
    }

    final public function boot(): void {
        $theme = Theme::get(); // Get the theme instance

        $theme->initialiseStyleSheet(); // Ensure the theme's stylesheet is enqueued
        $this->loadViewsFrom($theme->getPreference('views_path'), 'theme'); // Load views from the theme's views directory

        // Load routes from the theme's routes directory
        $routesPath = $theme->getPreference('routes_path');
        
        if (File::exists($routesPath) && File::isDirectory($routesPath)) {
            $routeFiles = File::files($routesPath);
            foreach ($routeFiles as $file) {
                if ($file->getExtension() === 'php') {
                    $this->loadRoutesFrom($file->getPathname());
                }
            }
        }
    }
}
