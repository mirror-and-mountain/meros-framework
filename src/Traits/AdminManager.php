<?php 

namespace MM\Meros\Traits;

/**
 * Used by the theme manager to initialise settings
 * pages in the Wordpress dashboard.
 */
trait AdminManager
{
    /**
     * Provides feature categories mapped to the applicable
     * options page in the Wordpress dashboard.
     *
     * @var array
     */
    protected array $options_map;

    /**
     * Can be enabled via the theme manager's configure() method.
     * Determines whether the framework adds a theme settings page
     * in the Wordpress dashboard.
     *
     * @var bool
     */
    protected bool $use_unified_settings_pages = false;

    /**
     * Uses the theme manager's theme slug to determine option
     * page's slugs for registration.
     *
     * @return void
     */
    private function setOptionsMap(): void
    {
        $themeSettingsPageID = $this->themeSlug . '_settings';

        $this->options_map = [
            'blocks'        => $themeSettingsPageID,
            'miscellaneous' => $themeSettingsPageID
        ];
    }

    /**
     * Sanitizes/validates the options map. Specifically,
     * we need to make sure a 'miscellaneous' category exists 
     * to use a fallback.
     *
     * @return void
     */
    private function sanitizeOptionsMap(): void
    {
        if ( !isset( $this->options_map['miscellaneous'] ) ) {
            $themeSettingsPageID = $this->themeSlug . '_settings';
            $this->options_map['miscellaneous'] = $themeSettingsPageID;
        }
    }

    /**
     * Initialises option pages if enabled.
     *
     * @return void
     */
    private function initialiseAdmin(): void
    {
        if ( !is_admin() ) {
            return;
        }
        
        if ( $this->use_unified_settings_pages ) {
            $this->initialiseAdminPages();
        }
    }

    /**
     * Hooks into Wordpress to add the necessary options pages.
     * At the moment, we're only using one utilising the
     * add_theme_page hook.
     *
     * @return void
     */
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

    /**
     * Used by features to map a feature's category to the relevant
     * options page.
     *
     * @return array
     */
    final public function getOptionsMap(): array
    {
        return $this->options_map ?? [];
    }
}