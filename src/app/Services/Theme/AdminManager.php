<?php 

namespace MM\Meros\app\Services\Theme;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Livewire;

class AdminManager {
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
     * The current WP Admin page.
     *
     * @var string
     */
    private string $currentPage = '';

    /**
     * Sets the current admin page
     *
     * @return string
     */
    private function setCurrentAdminPage(): string {
        $screen = get_current_screen();
        return $screen ? $screen->id : '';
    }

    /**
     * Initialises admin features.
     *
     * @return void
     */
    private function initialise(): void {
        $this->currentPage = $this->setCurrentAdminPage();
        $this->hidePlainPermalinkOption();

        foreach ($this->optionsPages as $_ => $config) {
            if (is_callable($config['callback'])) {
                call_user_func($config['callback'], $config);
            }
        }
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
     * Renders an options page in WP Admin.
     *
     * @param array $config
     * @return void
     */
    final public function renderOptionsPage(array $config): void {
        add_action('admin_menu', function () use ($config) {
            add_options_page(
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
     * Registers an options page to be added to the WP dashboard.
     *
     * @param string $slug
     * @param array $config
     * @return void
     */
    final public function registerOptionsPage(string $slug, array $config): void {
        $this->optionsPages[$slug] = $config;
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