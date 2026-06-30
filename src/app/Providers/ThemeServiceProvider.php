<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

use MM\Meros\App\BaseTheme as MerosTheme;
;
use MM\Meros\Support\ClassInfo;

use MM\Meros\Facades\Theme;

class ThemeServiceProvider extends ServiceProvider {

    use Concerns\HasViews, Concerns\HasRoutes, Concerns\HasLivewire;

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

    protected function beforeBoot(): void {
        // This method can be overridden by child classes to perform actions before the boot process
    }

    final public function boot(): void {
        $this->beforeBoot();

        $theme = Theme::get(); // Get the theme instance

        $theme->initialiseStyleSheet(); // Ensure the theme's stylesheet is enqueued
        
        // Register Livewire components
        $this->registerLivewireComponents($theme, 'theme');
        
        // Load views from the theme's views directory
        $this->registerViews($theme, 'theme');

        // Load routes from the theme's routes directory
        $this->registerRoutes($theme);

        $this->afterBoot();
    }

    protected function afterBoot(): void {
        // This method can be overridden by child classes to perform actions after the boot process
    }
}
