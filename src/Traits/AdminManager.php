<?php 

namespace MM\Meros\Traits;

trait AdminManager
{
    protected bool $use_unified_settings_pages = false;

    private function initialiseAdmin(): void
    {
        if ( !is_admin() ) {
            return;
        }
        
        if ( $this->use_unified_settings_pages ) {
            $this->initialiseAdminPages();
        }
    }

    private function initialiseAdminPages(): void
    {
        add_action('admin_menu', function () {
            add_theme_page(
                "{$this->themeName} Settings",
                'Settings',
                'manage_options',
                'meros_theme_settings',
                function () {
                    echo "<div class=\"wrap\">";
                    echo "<h1>" . esc_html($this->themeName) . " Settings</h1>";
                    echo "<form method='post' action='options.php'>";
                    settings_fields('meros_theme_settings');
                    do_settings_sections('meros_theme_settings');
                    submit_button();
                    echo "</form></div>";
                }            
            );
        });
    }
}