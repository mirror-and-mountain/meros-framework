<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Features\Settings\Setting;
use MM\Meros\App\Features\Settings\SettingsPage;
use MM\Meros\App\Features\Settings\SettingsSection;

trait HasSettings {
    /**
     * Whether this item should automatically discover settings configurations.
     *
     * @var bool
     */
    protected bool $discoverSettings = false;

    /**
     * Discovers settings pages, settings sections, and settings to be registered using 
     * the item's settings config file if available.
     *
     * @return void
     */
    protected function discoverSettings():void {
        if (! $this->discoverSettings) {
            return;
        }

        $settingsPath = $this->getPreference('settings_path');

        if (File::exists($settingsPath) && File::isFile($settingsPath)) {
            $pathInfo  = pathinfo($settingsPath);
            $extension = $pathInfo['extension'] ?? '';
            
            if ($extension !== 'php') {
                return;
            }

            $settingsDefs = include $settingsPath;

            if (!is_array($settingsDefs)) {
                return;
            }

            $pages    = $settingsDefs['pages'] ?? null;
            $sections = $settingsDefs['sections'] ?? null;
            $settings = $settingsDefs['settings'] ?? null;

            if (is_array($pages)) {
                foreach ($pages as $page) {
                    $this->makeSettingsPage($page);
                }
            }

            if (is_array($sections)) {
                foreach ($sections as $section) {
                    $this->makeSettingsSection($section);
                }
            }
            

            if (is_array($settings)) {
                foreach ($settings as $setting) {
                    $field = $setting['field'] ?? null;
                    $this->makeSetting($setting, $field);
                }
            }
        }
    }

    /**
     * Creates a SettingsPage instance for the item and registers it.
     * 
     * @param  array $config Config for the settings page.
     * 
     * @return SettingsPage The created SettingsPage instance.
     */
    protected function makeSettingsPage(array $config): SettingsPage {
        $existing = $this->getSettingsPage($config['menu_slug'] ?? '');

        if ($existing !== null) {
            return $existing;
        }

        return app(SettingsPage::class, ['source' => $this])->make($config);
    }

    /**
     * Creates a SettingsSection instance for the item and registers it.
     * 
     * @param  array $config Config for the settings section.
     * 
     * @return SettingsSection The created SettingsSection instance.
     */
    protected function makeSettingsSection(array $config): SettingsSection {
        $existing = $this->getSettingsSection($config['id'] ?? '');
        
        if ($existing !== null) {
            return $existing;
        }

        return app(SettingsSection::class, ['source' => $this])->make($config);
    }

    /**
     * Creates a Setting instance for the item and registers it.
     * 
     * @param  array $config Config for the setting.
     * @param  array|null $fieldConfig Optional config for the setting's associated field.
     * 
     * @return Setting The created Setting instance.
     */
    protected function makeSetting(array $config, array|null $field = null): Setting {
        $existing  = $this->getSetting($config['option_name'] ?? '');
        $withField = is_array($field);

        if ($existing !== null) {
            return $withField ? $existing->withField($field, true) : $existing;
        }

        if ($withField) {
            return app(Setting::class, ['source' => $this])->make($config)->withField($field);
        }

        return app(Setting::class, ['source' => $this])->make($config);
    }

    /**
     * Retrieves settings objects of the given type for this item.
     *
     * @param  string $type The type of settings objects to retrieve (e.g., 'settingsPages', 'settingsSections', 'settings').
     *
     * @return Collection A collection of settings objects of the specified type registered by this item.
     */
    protected function getSettingsObjects(string $type): Collection {
        return $this->registry
                ->get($type)
                ->where('source', $this) ?? collect([]);
    }

    /**
     * Returns array of settings page objects registered by the item.
     * 
     * @param  bool $readyOnly Whether to return only settings pages that are ready.
     *
     * @return Collection
     */
    final public function getSettingsPages(bool $readyOnly = false): Collection {
        if ($readyOnly) {
            return $this->getSettingsObjects('settingsPages')->where('ready', true);
        } else {
            return $this->getSettingsObjects('settingsPages');
        }
    }

    /**
     * Returns array of settings section objects registered by the item.
     * 
     * @param  bool $readyOnly Whether to return only settings sections that are ready.
     *
     * @return Collection
     */
    final public function getSettingsSections(bool $readyOnly = false): Collection {
        if ($readyOnly) {
            return $this->getSettingsObjects('settingsSections')->where('ready', true);
        } else {
            return $this->getSettingsObjects('settingsSections');
        }
    }

    /**
     * Returns array of all settings objects registered by the item.
     * 
     * @param  bool $readyOnly Whether to return only settings that are ready.
     *
     * @return Collection
     */
    final public function getSettings(bool $readyOnly = false): Collection {
        if ($readyOnly) {
            return $this->getSettingsObjects('settings')->where('ready', true);
        } else {
            return $this->getSettingsObjects('settings');
        }
    }

    /**
     * Returns a specific settings page object registered by the item.
     *
     * @param  string $handle The handle of the settings page to return.
     * 
     * @return SettingsPage|null
     */
    final public function getSettingsPage(string $handle): SettingsPage|null {
        $page = $this->getSettingsPages()->firstWhere('handle', $handle);

        return $page ?: null;
    }

    /**
     * Returns a specific settings section object registered by the item.
     *
     * @param  string $handle The handle of the settings section to return.
     * 
     * @return SettingsSection|null
     */
    final public function getSettingsSection(string $handle): SettingsSection|null {
        $section = $this->getSettingsSections()->firstWhere('handle', $handle);

        return $section ?: null;
    }

    /**
     * Returns a specific setting object registered by the item.
     *
     * @param  string $handle The handle of the setting to return.
     * 
     * @return Setting|null
     */
    final public function getSetting(string $handle): Setting|null {
        $setting = $this->getSettings()->firstWhere('handle', $handle);

        return $setting ?: null;
    }

    /**
     * Returns the value of a specific setting registered by the item.
     *
     * @param  string $handle The handle of the setting to return the value of.
     * @param  mixed  $default The default value to return if the setting isn't found or doesn't have a value.
     * 
     * @return mixed
     */
    final public function getSettingValue(string $handle): mixed {
        $setting = $this->getSetting($handle);
        return $setting ? $setting->getValue() : null;
    }
}