<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Str;

/**
 * Used by the theme manager class to set various
 * properties used in hooks and elsewhere.
 */
trait ContextManager
{
    /**
     * The theme name.
     */
    protected string $themeName;

    /**
     * The theme directory.
     */
    protected string $themeDir;

    /**
     * The theme's uri.
     */
    protected string $themeUri;

    /**
     * The theme's slug e.g. my_theme.
     */
    protected string $themeSlug;

    /**
     * The framework directory relative to the theme root.
     */
    private string $frameworkDir;

    /**
     * The framework uri.
     */
    private string $frameworkUri = '';

    /**
     * Sets theme identifier properties.
     */
    private function setContext(): void
    {
        $theme = wp_get_theme();
        $this->themeName = $theme->get('Name');
        $this->themeDir = get_stylesheet_directory();
        $this->themeUri = get_theme_file_uri();
        $this->themeSlug = Str::slug($this->themeName, '_');

        $this->frameworkDir = 'vendor/mirror-and-mountain/meros-framework/src/';
        $this->frameworkUri = trailingslashit($this->themeUri).$this->frameworkDir;
    }

    /**
     * Returns the theme name.
     */
    final public function getThemeName(): string
    {
        return $this->themeName;
    }

    /**
     * Returns the theme uri.
     */
    final public function getThemeUri(): string
    {
        return $this->themeUri;
    }

    /**
     * Returns the theme sluf.
     */
    final public function getThemeSlug(): string
    {
        return $this->themeSlug;
    }

    /**
     * Returns an array of theme properties.
     */
    final public function getThemeContext(): array
    {
        return [
            'name' => $this->themeName,
            'uri' => $this->themeUri,
            'slug' => $this->themeSlug,
        ];
    }
}
