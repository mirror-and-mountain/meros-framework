<?php

namespace MM\Meros\App\Providers;

use Illuminate\Support\Facades\Config;
use MM\Meros\Contracts\MerosServiceProvider;

use MM\Meros\App\BaseTheme;

use MM\Meros\Support\ClassInfo;

class ThemeServiceProvider extends MerosServiceProvider {

    /**
     * The instance of the theme being registered.
     *
     * @var BaseTheme
     */
    private BaseTheme $instance;

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Registers the theme's services.
     *
     * @return void
     */
    final public function register(): void {
        $themeClass = Config::get('theme.theme_class');
        $themeClass = ClassInfo::get($themeClass);

        if ($themeClass->extends(BaseTheme::class)) {
            $this->app->singleton(BaseTheme::class, function () use ($themeClass) {
                return new $themeClass->name();
            });
            
            $this->app->alias(BaseTheme::class, 'meros.theme');

            // Instantiate the theme to trigger the constructor and set up the theme
            $this->instance = $this->app->make(BaseTheme::class)->get();
        }

        defined('MEROS') || define('MEROS', true);
    }

    // =========================================================================
    // Booting
    // =========================================================================
    
    /**
     * Boots the theme's services.
     *
     * @return void
     */
    final public function boot(): void {
        // Run the beforeBoot method
        $this->beforeBoot();

        // Initialise the theme
        $theme = $this->instance;
        
        // Register Livewire components
        $this->registerLivewireComponents($theme, 'theme');
        
        // Load views from the theme's views directory
        $this->registerViews($theme, 'theme');

        // Load routes from the theme's routes directory
        $this->registerRoutes($theme);

        // Call the theme's configure method
        $theme->configure();

        // Run the afterBoot method
        $this->afterBoot();
    }

    /**
     * This method can be overridden by child classes to perform actions before the boot process.
     *
     * @return void
     */
    protected function beforeBoot(): void {
        // This method can be overridden by child classes to perform actions before the boot process
    }

    /**
     * This method can be overridden by child classes to perform actions after the boot process.
     *
     * @return void
     */
    protected function afterBoot(): void {
        // This method can be overridden by child classes to perform actions after the boot process
    }
}
