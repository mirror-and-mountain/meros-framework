<?php

namespace MM\Meros\Traits\Theme;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Livewire;

use MM\Meros\Components\AdminMigrationButton;

/**
 * Used by the theme manager to initialise settings
 * pages in the Wordpress dashboard.
 */
trait AdminManager {
    /**
     * Available options pages for WP Admin.
     *
     * @var array
     */
    protected array $optionsPages = [];

    /**
     * An array of settings registered by all features
     * and extensions in the theme.
     *
     * @var array
     */
    protected array $registeredSettings = [];

    /**
     * An array of settings sections registered by
     * all features and extensions in the theme.
     *
     * @var array
     */
    protected array $registeredSettingsSections = [];

    /**
     * Sets optionsPages array.
     *
     * @return void
     */
    private function setOptionsPages(): void {
        $this->optionsPages = [
            'theme_settings' => [
                'page_title' => "{$this->themeName} Settings",
                'menu_title' => 'Settings',
                'menu_slug'  => 'theme_settings',
                'tabs'       => ['blocks', 'scripts_and_styles', 'miscellaneous'],
                'capability' => 'manage_options',
                'callback'   => [$this, 'renderThemeSettingsPage'],
            ],
            'theme_features' => [
                'page_title' => 'Theme Features',
                'menu_title' => 'Features',
                'menu_slug'  => 'theme_features',
                'tabs'       => ['features', 'experimental_features'],
                'capability' => 'manage_options',
                'callback'   => [$this, 'renderThemeFeaturesPage'],
            ],
        ];

        foreach($this->optionsPages as $config) {
            $tabs = [];
            foreach ($config['tabs'] as $tab) {
                $tabs[$tab] = [];
            }

            $this->registeredSettingsSections[$config['menu_slug']] = [
                'tabs' => $tabs,
            ];
        }
    }

    /**
     * Initialises option pages if enabled.
     * Enqueues admin scripts and styles.
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
     * 
     * @return void
     */
    private function enqueueAdminScripts(): void {
        $assetsUri = trailingslashit($this->frameworkUri) . 'assets/build/admin/';
        $assetsDir = trailingslashit(get_stylesheet_directory()) . $this->frameworkDir . 'assets/build/admin/';

        add_action('admin_enqueue_scripts', function () use ($assetsUri, $assetsDir) {
            if (isset($_GET['page']) && $_GET['page'] === 'theme_features') {
                $this->initialiseLivewire(true);
                Livewire::component('meros.admin-migration-button', AdminMigrationButton::class);
                
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
            }
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

    /**
     * Returns all the theme's features' registered setting keys.
     *
     * @return array
     */
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
     *
     * @param array $config
     * @return void
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

    /**
     * Renders the theme features page.
     *
     * @param array $config
     * @return void
     */
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

    /**
     * Renders settings page tabs.
     *
     * @param array $config
     * @param boolean $showSumit
     * @return void
     */
    private function renderSettingsPageTabs(array $config, bool $showSumit = true): void {
        $tabLabels = [];

        foreach ($config['tabs'] as $tab) {
            $tabLabels[$tab] = Str::headline(Str::replace('_', ' ', $tab));
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html($config['page_title']) ?></h1>
            <?php
            $settingsIntro = esc_html(apply_filters("{$config['menu_slug']}_settings_intro", ''));
            $settingsIntroHtml = $settingsIntro !== '' ? "<p>{$settingsIntro}</p>" : '';

            echo $settingsIntroHtml;
            $current_tab = isset($_GET['tab'], $tabLabels[$_GET['tab']]) ? $_GET['tab'] : array_key_first($tabLabels);
            ?>
            <form method='post' action='options.php'>
                <nav class="nav-tab-wrapper">
                    <?php
                    foreach ($tabLabels as $tab => $label) {
                        if ($this->registeredSettingsSections[$config['menu_slug']]['tabs'][ $tab ] === []) {
                            continue; // Don't show the tab if it has no registered settings sections
                        }

                        $current = $tab === $current_tab ? ' nav-tab-active' : '';
                        $url = add_query_arg(['page' => $config['menu_slug'], 'tab' => $tab], '');
                        echo "<a class=\"nav-tab{$current}\" href=\"{$url}\">{$label}</a>";
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
     * 
     * @return void
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

    /**
     * Returns available options pages.
     *
     * @return array
     */
    final public function getOptionsPages(): array {
        return $this->optionsPages ?? [];
    }

    /**
     * Adds an options page tab to an options page.
     *
     * @param string $page
     * @param string $tab
     * @return void
     */
    final public function addOptionsPageTab(string $page, string $tab): void {
        $this->optionsPages[ $page ]['tabs'][] = $tab;

        if (
            array_key_exists($page, $this->registeredSettingsSections) &&
            is_array($this->registeredSettingsSections[$page]['tabs'] ?? null) &&
            !array_key_exists($tab, $this->registeredSettingsSections[$page]['tabs'])
        ) {
            $this->registeredSettingsSections[$page]['tabs'][ $tab ] = [];
        }
    }

    /**
     * Records a feature settings section.
     *
     * @param string $id
     * @param array $config
     * @return void
     */
    final public function addSettingsSection(string $id, array $config): void {
        $page     = $config['page'] ?? null;
        $tab      = $config['tab'] ?? null;
        $settings = $config['settings'] ?? [];
    
        if (!isset($page, $tab, $settings)) {
            return;
        }

        if (
            in_array($tab, array_keys($this->registeredSettingsSections[$page]['tabs'] ?? []), true) &&
            is_array($this->registeredSettingsSections[$page]['tabs'][$tab])
        ) {
            if (!in_array($id, array_keys($this->registeredSettingsSections[$page]['tabs'][$tab] ?? []), true)) {
                $this->registeredSettingsSections[$page]['tabs'][$tab][$id] = $settings;
            }
        }
    }

    /**
     * Updates a registered feature settings section with a new setting.
     *
     * @param string $id
     * @param string $settingName
     * @return void
     */
    final public function updateSettingsSection(string $id, string $settingName): void {
        foreach ($this->registeredSettingsSections as $page => $config) {
            foreach ($config['tabs'] as $tab => $sections) {
                if (
                    array_key_exists($id, $sections) &&
                    !in_array($settingName, $sections[$id], true)
                ) {
                    $this->registeredSettingsSections[$page]['tabs'][$tab][$id][] = $settingName;
                    return;
                }
            }
        }
    }

    /**
     * Returns all registered settings sections.
     *
     * @return array
     */
    final public function getRegisteredSettingsSections(): array {
        return $this->registeredSettingsSections;
    }

    /**
     * Returns a registered settings section by ID.
     *
     * @param string $page
     * @param string $tab
     * @param string $id
     * @return array|null
     */
    final public function getRegisteredSettingsSection(string $page, string $tab, string $id): ?array {
        return $this->registeredSettingsSections[$page]['tabs'][$tab][$id] ?? null;
    }

    /**
     * Returns all registered settings.
     *
     * @return array
     */
    final public function getRegisteredSettings(): array {
        return $this->registeredSettings;
    }
}
