<?php

namespace MM\Meros\App;

use MM\Meros\Services\Contracts\FeatureProvider;

abstract class Theme extends FeatureProvider {
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
        add_action('wp_enqueue_scripts', function () {
            $handle = $this->handle . '_style'; // e.g. meros_style.
            wp_enqueue_style(
                $handle,
                get_stylesheet_uri(),
                [],
                filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css')
            );
        });
    }

    /**
     * Gets the instance of the theme.
     *
     * @return Theme
     */
    final public function get(): Theme {
        return $this;
    }
}
