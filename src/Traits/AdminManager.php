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
                    ?>
                    <div class="wrap">
                        <h1><?php echo esc_html($this->themeName)?> Settings</h1>
                        <?php
                            $tabs = [
                                'blocks'        => 'Blocks',
                                'miscellaneous' => 'Miscellaneous'
                            ];
                            $current_tab = isset( $_GET['tab'], $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : array_key_first( $tabs );
                        ?>
                        <form method='post' action='options.php'>
                            <nav class="nav-tab-wrapper">
                                <?php
                                    foreach ( $tabs as $tab => $name ) {
                                        $current = $tab === $current_tab ? ' nav-tab-active' : '';
                                        $url     = add_query_arg( array( 'page' => 'meros_theme_settings', 'tab' => $tab ), '' );
                                        echo "<a class=\"nav-tab{$current}\" href=\"{$url}\">{$name}</a>";
                                    }
                                ?>
                            </nav>
                        <?php
                            settings_fields("meros_theme_settings_{$current_tab}");
                            do_settings_sections("meros_theme_settings_{$current_tab}");
                            submit_button();
                        ?>
                        </form>
                    </div>
                    <?php
                }            
            );
        });
    }
}