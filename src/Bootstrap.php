<?php 

namespace MM\Meros;

use Roots\Acorn\Application as RootsApplication;
use MM\Meros\App\Providers\ThemeServiceProvider;
use MM\Meros\App\Services\Theme\ThemeManager;

class Bootstrap {
    public static string $authorName;
    public static string $authorDesc;
    public static string $authorUrl;
    public static string $authorSupportUrl;
    
    final public static function bootstrap(
        array  $providers = [],
        string $authorName = 'Unknown',
        string $authorDesc = '',
        string $authorUrl = '',
        string $authorSupportUrl = ''
    ): void {
        if (class_exists(RootsApplication::class)) {
            self::$authorName = $authorName;
            self::$authorDesc = $authorDesc;
            self::$authorUrl = $authorUrl;
            self::$authorSupportUrl = $authorSupportUrl;

            add_action('after_setup_theme', function () use ($providers) {
                $providers = array_merge([ThemeServiceProvider::class], $providers);
                $root      = get_stylesheet_directory();

                RootsApplication::configure($root)
                    ->withProviders($providers)
                    ->withRouting(wordpress: true)
                    ->boot();
            }, 0);

            add_action('meros_theme_ready', function (ThemeManager $theme) {
                $theme->addAuthorInfo([
                    'name'        => self::$authorName,
                    'description' => self::$authorDesc,
                    'url'         => self::$authorUrl,
                    'support_url' => self::$authorSupportUrl,
                ]);
            }, 0);
        }
    }
}