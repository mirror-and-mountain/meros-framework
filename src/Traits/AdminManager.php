<?php 

namespace MM\Meros\Traits;

trait AdminManager
{
    protected array $options_map;
    protected bool  $use_unified_settings_pages = false;

    private function setOptionsMap(): void
    {
        $themeSettingsPageID = $this->themeSlug . '_settings';

        $this->options_map = [
            'blocks'        => $themeSettingsPageID,
            'miscellaneous' => $themeSettingsPageID
        ];
    }

    private function sanitizeOptionsMap(): void
    {
        if ( !isset( $this->options_map['miscellaneous'] ) ) {
            $themeSettingsPageID = $this->themeSlug . '_settings';
            $this->options_map['miscellaneous'] = $themeSettingsPageID;
        }
    }

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
                "{$this->themeSlug}_settings",
                function () {
                    $tabs = [];
                    foreach ( $this->options_map as $tab => $_ ) {
                        $tabs[ $tab ] = ucfirst( $tab );
                    }
                    ?>
                    <div class="wrap">
                        <h1><?php echo esc_html($this->themeName)?> Settings</h1>
                        <?php
                            $settingsIntro     = esc_html( apply_filters("{$this->themeSlug}_settings_intro", '') );
                            $settingsIntroHtml = $settingsIntro !== '' ? "<p>{$settingsIntro}</p>" : '';
                            
                            echo $settingsIntroHtml;
                            $current_tab = isset( $_GET['tab'], $tabs[ $_GET['tab'] ] ) ? $_GET['tab'] : array_key_first( $tabs );
                        ?>
                        <form method='post' action='options.php'>
                            <nav class="nav-tab-wrapper">
                                <?php
                                    foreach ( $tabs as $tab => $name ) {
                                        $current = $tab === $current_tab ? ' nav-tab-active' : '';
                                        $url     = add_query_arg( array( 'page' => "{$this->themeSlug}_settings", 'tab' => $tab ), '' );
                                        echo "<a class=\"nav-tab{$current}\" href=\"{$url}\">{$name}</a>";
                                    }
                                ?>
                            </nav>
                        <?php
                            settings_fields("{$this->themeSlug}_settings_{$current_tab}");
                            do_settings_sections("{$this->themeSlug}_settings_{$current_tab}");
                            submit_button();
                        ?>
                        </form>
                    </div>
                    <?php
                }            
            );
        });
    }

    final public function getOptionsMap(): array
    {
        return $this->options_map ?? [];
    }
}