<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;

trait ContextManager
{
    protected string $themeName;
    protected string $themeUri;
    protected string $themeSlug;

    private function setContext(): void
    {
        $theme           = wp_get_theme();
        $this->themeName = $theme->get('Name');
        $this->themeUri  = get_theme_file_uri();
        $this->themeSlug = Str::slug( $this->themeName, '_' );
    }

    final public function getThemeName(): string
    {
        return $this->themeName;
    }

    final public function getThemeUri(): string
    {
        return $this->themeUri;
    }

    final public function getThemeSlug(): string
    {
        return $this->themeSlug;
    }

    final public function getThemeContext(): array
    {
        return [
            'name' => $this->themeName,
            'uri'  => $this->themeUri,
            'slug' => $this->themeSlug
        ];
    }
}