<?php 

namespace MM\Meros\App\Services\Theme;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Facades\Theme;
use MM\Meros\App\Services\Theme\Concerns\MigrationManager;

class AdminManager {
    /**
     * Available options pages for WP Admin.
     *
     * @var array
     */
    protected array $settingsPages = [];

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
     * An array mapping options page areas to their corresponding WP functions.
     *
     * @var array
     */
    private array $settingsPageFunctions = [
        'options' => 'add_options_page',
        'theme'   => 'add_theme_page',
    ];

    /**
     * The current WP Admin page.
     *
     * @var string
     */
    private string $currentPage = '';

    /**
     * Config for WP Admin.
     *
     * @var array
     */
    private array $config = [];

    use MigrationManager;

    final public function __construct() {
        $configPath = dirname(__DIR__, 3) . '/config/admin.php';
        
        $this->config = File::exists($configPath)
            ? include_once $configPath
            : [];

        if (is_array($this->config['settings_pages'] ?? null)) {
            foreach ($this->config['settings_pages'] as $slug => $config) {
                $this->registerSettingsPage($slug, $config);
            }
        }

        foreach($this->settingsPages as $config) {
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
     * Initialises admin features.
     *
     * @return void
     */
    final public function initialise(): void {
        $this->enqueueAdminAssets();
        $this->hidePlainPermalinkOption();

        foreach ($this->settingsPages as $_ => $config) {
            if (is_callable($config['callback'])) {
                call_user_func($config['callback'], $config);
            }
        }
    }

    /**
     * Enqueues admin assets.
     *
     * @return void
     */
    private function enqueueAdminAssets(): void {
        add_action('admin_enqueue_scripts', function() {
            $path = wp_normalize_path( 'assets/build/admin/style-index.css' );
            wp_enqueue_style(
                'meros-admin',
                Theme::getFrameworkUri() . $path,
                [],
                filemtime(Theme::getFrameworkPath() . $path)
            );
        });
    }

    /**
     * Hides the "Plain" permalink option in WP Admin.
     * 
     * @return void
     */
    private function hidePlainPermalinkOption(): void {
        add_action( 'admin_print_scripts', function() {
            ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const plainOption = document.querySelector('#permalink-input-plain');
                if ( plainOption ) {
                    plainOption.parentElement.remove();
                }
            });
            </script>
            <?php
        });
    }

    /**
     * Renders a settings page in WP Admin.
     *
     * @param array $config
     * @return void
     */
    final public function renderSettingsPage(array $config): void {
        add_action('admin_menu', function () use ($config) {
            $area = $config['area'] ?? 'options';
            
            $areaFunction = $this->settingsPageFunctions[$area] ?? null;

            if ($areaFunction === null) {
                return;
            }

            $areaFunction(
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
     * Renders settings page tabs.
     *
     * @param array $config
     * @param boolean $showSubmit
     * @return void
     */
    private function renderSettingsPageTabs(array $config, bool $showSubmit = true): void {
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
                if ($showSubmit) {
                    submit_button();
                }
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Records settings from the given source (usually a package)
     *
     * @param string $source
     * @param array  $settings
     * @return void
     */
    final public function addRegisteredSettings(string $source, array $settings): void {
        $this->registeredSettings[$source] = $settings;
    }

    /**
     * Registers an Settings page to be added to the WP dashboard.
     *
     * @param string $slug
     * @param array $config
     * @return void
     */
    final public function registerSettingsPage(string $slug, array $config): void {
        $this->settingsPages[$slug] = $config;
    }

    /**
     * Returns available options pages.
     *
     * @return array
     */
    final public function getOptionsPages(): array {
        return $this->settingsPages ?? [];
    }

    /**
     * Adds an options page tab to an options page.
     *
     * @param string $page
     * @param string $tab
     * @return void
     */
    final public function addOptionsPageTab(string $page, string $tab): void {
        $this->settingsPages[ $page ]['tabs'][] = $tab;

        if (
            array_key_exists($page, $this->registeredSettingsSections) &&
            is_array($this->registeredSettingsSections[$page]['tabs'] ?? null) &&
            !array_key_exists($tab, $this->registeredSettingsSections[$page]['tabs'])
        ) {
            $this->registeredSettingsSections[$page]['tabs'][ $tab ] = [];
        }
    }

    /**
     * Registers a feature settings section.
     *
     * @param string $id
     * @param array  $config
     * @return void
     */
    final public function registerSettingsSection(string $id, array $config): void {
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