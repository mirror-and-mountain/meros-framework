<?php

namespace MM\Meros\App;

use MM\Meros\Services\Contracts\FeatureProvider;

abstract class BaseTheme extends FeatureProvider {
    /**
     * Adds a Wordpress theme support.
     * 
     * @param  string $support
     * @param  mixed  ...$args
     * 
     * @return void
     */
    protected function addThemeSupport(string $support, mixed ...$args): void {
        add_theme_support($support, $args);
    }

    /**
     * Initialises the theme stylesheet
     * 
     * @return void
     */
    public function initialiseStyleSheet(): void {
        $handle = $this->handle . '_style'; // e.g. meros_style.
        $uri = get_stylesheet_uri();
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

    /**
     * Gets the instance of the theme.
     *
     * @return BaseTheme
     */
    final public function get(): BaseTheme {
        return $this;
    }
}
