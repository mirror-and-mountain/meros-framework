<?php 

namespace MM\Meros\Traits;

trait AdminManager
{
    protected bool $use_unified_settings_pages = false;

    private function initialiseAdminPages(): void
    {
        add_action('admin_menu', function () {
            add_theme_page(
                "{$this->themeName} Settings",
                'Settings',
                'manage_options',
                'meros_theme_settings',
                function () {
                    echo "<div class=\"wrap\"><h1>" . esc_html( $this->themeName ) . " Settings</h1><p>I am an options page.</p></div>";
                }            
            );
        });
    }
}