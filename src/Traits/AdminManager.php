<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Arr;

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
     * Whether the migrations settings page should be added.
     *
     * @var bool
     */
    protected bool $register_migrations_page = false;

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
            'styles'        => $themeSettingsPageID,
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
            $this->initialiseThemeSettingsPage();
        }

        if ($this->register_migrations_page) {
            $this->initialiseMigrationsSettingsPage();
        }
    }

    /**
     * Hooks into Wordpress to add the necessary options pages.
     * At the moment, we're only using one utilising the
     * add_theme_page hook.
     *
     * @return void
     */
    private function initialiseThemeSettingsPage(): void
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

    private function initialiseMigrationsSettingsPage(): void
    {
        $features = Arr::flatten( $this->features );

        $features = Arr::where( $features, function($feature) {
            return property_exists( $feature, 'hasMigrations' ) && $feature->hasMigrations === true;
        });

        if ( count( $features ) === 0 ) {
            return;
        }

        add_action('admin_menu', function() use ($features) {
            add_options_page(
                'Database',
                'Database',
                'manage_options',
                $this->themeSlug . '_db_migrations',
                function() use ($features) {
                    ?>
                    <div class="wrap">
                        <h1>Database Migrations</h1>
                        <?php 
                            foreach ($features as $feature) {
                                $featureName = str_replace('_', ' ', $feature->getName());
                                $featureName = ucwords($featureName);
                                ?>
                                <div style="margin-bottom: 2rem">
                                    <h2><?php echo esc_html($featureName); ?></h2>
                                    <form method="post" action="">
                                        <?php wp_nonce_field( $feature->getName() . '_migrate_action', $feature->getName() . '_migrate_nonce' ); ?>
                                        <input type="hidden" name="feature_name" value="<?php echo esc_attr($feature->getName()); ?>">
                                        <p>Run database migrations for the <?php echo esc_html($featureName); ?> feature.</p>
                                        <?php
                                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations']) && isset($_POST['feature_name']) && $_POST['feature_name'] === $feature->getName()) {
                                            if (isset($_POST[$feature->getName() . '_migrate_nonce']) && wp_verify_nonce($_POST[$feature->getName() . '_migrate_nonce'], $feature->getName() . '_migrate_action')) {
                                                if (method_exists($feature, 'runMigrations')) {
                                                    $feature->runMigrations();
                                                    echo '<div class="notice notice-success"><p>Migrations ran successfully for ' . esc_html($featureName) . '.</p></div>';
                                                } else {
                                                    echo '<div class="notice notice-error"><p>runMigrations method not found on ' . esc_html($featureName) . '.</p></div>';
                                                }
                                            } else {
                                                echo '<div class="notice notice-error"><p>Invalid nonce. Please try again.</p></div>';
                                            }
                                        }
                                        ?>
                                        <button type="submit" name="run_migrations" class="button button-primary">Run Migrations</button>
                                    </form>
                                </div>
                                <?php
                            }
                        ?>
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