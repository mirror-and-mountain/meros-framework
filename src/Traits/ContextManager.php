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
     *
     * @var string
     */
    protected string $themeName;

    /**
     * The theme's uri.
     *
     * @var string
     */
    protected string $themeUri;

    /**
     * The theme's slug e.g. my_theme.
     *
     * @var string
     */
    protected string $themeSlug;

    /**
     * Sets theme identifier properties.
     *
     * @return void
     */
    private function setContext(): void
    {
        $theme           = wp_get_theme();
        $this->themeName = $theme->get('Name');
        $this->themeUri  = get_theme_file_uri();
        $this->themeSlug = Str::slug( $this->themeName, '_' );
    }

    /**
     * Returns the theme name.
     *
     * @return string
     */
    final public function getThemeName(): string
    {
        return $this->themeName;
    }

    /**
     * Returns the theme uri.
     *
     * @return string
     */
    final public function getThemeUri(): string
    {
        return $this->themeUri;
    }

    /**
     * Returns the theme sluf.
     *
     * @return string
     */
    final public function getThemeSlug(): string
    {
        return $this->themeSlug;
    }

    /**
     * Returns an array of theme properties.
     *
     * @return array
     */
    final public function getThemeContext(): array
    {
        return [
            'name' => $this->themeName,
            'uri'  => $this->themeUri,
            'slug' => $this->themeSlug
        ];
    }
}