<?php 

namespace MM\Meros\Traits;

trait ContextManager
{
    protected string $themeName;
    protected string $themeUri;

    private function setContext(): void
    {
        $theme           = wp_get_theme();
        $this->themeName = $theme->get('Name');
        $this->themeUri  = get_theme_file_uri();
    }
}