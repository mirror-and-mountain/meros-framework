<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Used by the theme manager to initialise settings
 * pages in the Wordpress dashboard.
 */
trait AdminManager {
    protected array $optionsPages = [];
    protected static array $registeredSettings = [];

    private function setOptionsPages(): void {
        $this->optionsPages = [
            'theme_settings' => [
                'page_title' => "{$this->themeName} Settings",
                'menu_title' => 'Settings',
                'menu_slug' => 'theme_settings',
                'tabs' => ['blocks', 'styles', 'miscellaneous'],
                'capability' => 'manage_options',
                'callback' => [$this, 'renderThemeSettingsPage'],
            ],
            'theme_features' => [
                'page_title' => 'Theme Features',
                'menu_title' => 'Features',
                'menu_slug' => 'theme_features',
                'tabs' => ['features', 'experimental_features'],
                'capability' => 'manage_options',
                'callback' => [$this, 'renderThemeFeaturesPage'],
            ],
        ];
    }

    /**
     * Initialises option pages if enabled.
     * Applies admin scripts and styles.
     * 
     * @return void
     */
    private function initialiseAdmin(): void {
        if (! is_admin()) {
            return;
        }

        $this->enqueueAdminScripts();
        $this->hidePlainPermalinkOption();

        foreach ($this->optionsPages as $_ => $config) {
            if (is_callable($config['callback'])) {
                call_user_func($config['callback'], $config);
            }
        }
    }

    /**
     * Enqueues admin scripts and styles.
     */
    private function enqueueAdminScripts(): void {
        $assetsUri = trailingslashit($this->frameworkUri) . 'assets/build/admin/';
        $assetsDir = trailingslashit(get_stylesheet_directory()) . $this->frameworkDir . 'assets/build/admin/';

        add_action('admin_enqueue_scripts', function () use ($assetsUri, $assetsDir) {
            wp_enqueue_script(
                'mm-meros-toggle',
                $assetsUri . 'index.js',
                [],
                filemtime($assetsDir . 'index.js'),
                true
            );

            wp_enqueue_style(
                'mm-meros-admin-style',
                $assetsUri . 'style-index.css',
                [],
                filemtime($assetsDir . 'style-index.css')
            );
        });

        add_action('wp_ajax_meros_toggle_feature', function () {
            $option = sanitize_key($_POST['option'] ?? '');
            $nonce = $_POST['nonce'] ?? '';

            if (! $option || ! wp_verify_nonce($nonce, 'mm_meros_toggle_' . $option)) {
                wp_send_json_error('Invalid request');
            }

            // Let your existing sanitize_callback run
            $current = (bool) get_option($option);
            update_option($option, $current ? '0' : '1');

            $new_value = (bool) get_option($option);
            $label = $new_value ? 'Enabled' : 'Enable';
            $next_value = $new_value ? '0' : '1';

            wp_send_json_success([
                'value' => (int) $new_value,
                'label' => $label,
                'title' => $new_value ? 'Disable' : 'Enable',
                'next_value' => $next_value,
                'nonce' => wp_create_nonce('mm_meros_toggle_' . $option),
            ]);
        });
    }

    final public function getRegisteredSettingKeys(): array {
        $settings = [];
        foreach (self::$registeredSettings as $_ => $optionGroups) {
            foreach ($optionGroups as $optionGroup => $options) {
                foreach ($options as $optionKey => $_) {
                    $settings[] = $optionKey;
                }
            }
        }
        return $settings;
    }

    /**
     * Adds a theme settings page to the WP dashboard.
     */
    private function renderThemeSettingsPage(array $config): void {
        add_action('admin_menu', function () use ($config) {
            add_theme_page(
                $config['page_title'],
                $config['menu_title'],
                $config['capability'],
                $config['menu_slug'],
                function () use ($config) {
                    $this->renderSettingsPageTabs($config);
                }
            );
        });
    }

    private function renderThemeFeaturesPage(array $config): void {
        add_action('admin_menu', function () use ($config) {
            add_options_page(
                $config['page_title'],
                $config['menu_title'],
                $config['capability'],
                $config['menu_slug'],
                function () use ($config) {
                    $this->renderSettingsPageTabs($config, false);
                },
                1
            );
        });
    }

    private function renderSettingsPageTabs(array $config, bool $showSumit = true): void {
        foreach ($config['tabs'] as $tab) {
            $tabs[$tab] = Str::ucfirst(Str::replace('_', ' ', $tab));
        }
?>
        <div class="wrap">
            <h1><?php echo esc_html($config['page_title']) ?></h1>
            <?php
            $settingsIntro = esc_html(apply_filters("{$config['menu_slug']}_settings_intro", ''));
            $settingsIntroHtml = $settingsIntro !== '' ? "<p>{$settingsIntro}</p>" : '';

            echo $settingsIntroHtml;
            $current_tab = isset($_GET['tab'], $tabs[$_GET['tab']]) ? $_GET['tab'] : array_key_first($tabs);
            ?>
            <form method='post' action='options.php'>
                <nav class="nav-tab-wrapper">
                    <?php
                    foreach ($tabs as $tab => $name) {
                        $current = $tab === $current_tab ? ' nav-tab-active' : '';
                        $url = add_query_arg(['page' => $config['menu_slug'], 'tab' => $tab], '');
                        echo "<a class=\"nav-tab{$current}\" href=\"{$url}\">{$name}</a>";
                    }
                    ?>
                </nav>
                <?php
                settings_fields("{$config['menu_slug']}_{$current_tab}");
                do_settings_sections("{$config['menu_slug']}_{$current_tab}");
                if ($showSumit) {
                    submit_button();
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Adds a database migrations page to the WP dashboard.
     */
    private function initialiseMigrationsSettingsPage(): void {
        if ($this->migrations_page_registered) {
            return;
        }

        $features = Arr::flatten($this->features);

        $features = Arr::where($features, function ($feature) {
            return property_exists($feature, 'hasMigrations') && $feature->hasMigrations === true;
        });

        if (count($features) === 0) {
            return;
        }

        add_action('admin_menu', function () use ($features) {
            add_options_page(
                'Database',
                'Database',
                'manage_options',
                $this->themeSlug . '_db_migrations',
                function () use ($features) {
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
                                <?php wp_nonce_field($feature->getName() . '_migrate_action', $feature->getName() . '_migrate_nonce'); ?>
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

        $this->migrations_page_registered = true;
    }

    final public function getOptionsPages(): array {
        return $this->optionsPages ?? [];
    }

    final public function addOptionsPageTab(string $page, string $tab): void {
        $this->optionsPages[$page]['tabs'][] = $tab;
    }
}
