<?php

namespace MM\Meros\App;

use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Contracts\Providers\Concerns\IsNonFrameworkProvider;

abstract class BaseTheme extends Provider {
    use IsNonFrameworkProvider;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $themeInfo = \wp_get_theme();

        $this->setName($themeInfo->get('Name'));
        $this->setDescription($themeInfo->get('Description'));
        $this->setAuthor($themeInfo->get('Author'));
        $this->setAuthorUrl($themeInfo->get('AuthorURI'));
        $this->setPath(\get_stylesheet_directory());
        $this->setUri(\get_stylesheet_directory_uri());

        $this->enqueueStyleSheet();
    }

    /**
     * Enqueues the theme's stylesheet
     * 
     * @return void
     */
    private function enqueueStyleSheet(): void {
        $handle  = $this->getHandle() . '_style'; // e.g. meros_style.
        $uri     = get_stylesheet_uri();
        $version = filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css');

        add_action('wp_enqueue_scripts', function () use ($handle, $uri, $version) {
            wp_enqueue_style(
                $handle,
                $uri,
                [],
                $version
            );
        });

        add_action('enqueue_block_editor_assets', function () use ($handle, $uri, $version) {
            wp_enqueue_style(
                $handle,
                $uri,
                [],
                $version
            );
        });
    }

    // =========================================================================
    // Settings Management
    // =========================================================================

    /**
     * Resolves the settings container for the theme.
     *
     * @param SettingsContainers $register The SettingsContainers register.
     *
     * @return SettingsContainer The settings container for the theme.
     */
    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        return $register->get('meros_theme_settings', $this) ?? 
               $register
                ->checkout($this)
                ->makeFrom('meros_theme_settings');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Adds a Wordpress theme support.
     * 
     * @param  string $support
     * @param  mixed  ...$args
     * 
     * @return void
     */
    final protected function addThemeSupport(string $support, mixed ...$args): void {
        add_theme_support($support, $args);
    }
}
