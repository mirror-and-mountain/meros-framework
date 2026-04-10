<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Features\Settings\Setting;
use MM\Meros\App\Features\Settings\AdminPage;
use MM\Meros\App\Features\Settings\SettingsSection;

trait HasSettings {
    /**
     * The item's consolidated setting instance.
     *
     * @var Setting
     */
    private ?Setting $itemSetting = null;

    /**
     * Discovers settings to be registered using 
     * the item's settings config file if available.
     *
     * @return void
     */
    protected function discoverSettings():void {
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

            foreach ($settingsDefs as $setting) {
                $optionName  = $setting['option_name'] ?? null;
                $type        = $setting['type'] ?? null;
                $fieldType   = $setting['field_type'] ?? null;
                $args        = $setting['args'] ?? [];

                if (!$optionName || !$type) {
                    continue;
                }

                $this->addItemSetting($optionName, $type, $fieldType, $args);
            }
        }
    }

    private function getItemSettingObject(): Setting {
        if ($this->itemSetting !== null) {
            return $this->itemSetting;
        }

        $name  = $this->handle . '_settings';
        $group = $this->handle . '_settings';

        $this->itemSetting = $this->makeSetting()->object($group, $name);
        return $this->itemSetting;
    }

    private function addItemSetting(
        string       $optionName,
        string       $type,
        string|false $fieldType = false,
        array        $args = []
    ): void {
        $setting = $this->getItemSettingObject();
        $subSetting = $setting->addSubItem('settings', $optionName, $type, $args);

        if ($fieldType !== false) {
            $subSetting->withField($fieldType);
        }

    }

    /**
     * Creates an AdminPage instance for the item and registers it.
     * 
     * @param  string $slug The slug of the admin page.
     * 
     * @return AdminPage The created AdminPage instance.
     */
    protected function makeAdminPage(string $slug): AdminPage {
        $existing = $this->getAdminPage($slug);

        if ($existing !== null) {
            return $existing;
        }

        return app(AdminPage::class, ['source' => $this, 'slug'  => $slug]);
    }

    /**
     * Creates a SettingsSection instance for the item and registers it.
     * 
     * @param  string $id The ID of the settings section.
     * 
     * @return SettingsSection The created SettingsSection instance.
     */
    protected function makeSettingsSection(string $id): SettingsSection {
        $existing = $this->getSettingsSection($id);
        
        if ($existing !== null) {
            return $existing;
        }

        return app(SettingsSection::class, ['source' => $this, 'id' => $id]);
    }

    /**
     * Creates a Setting instance for the item and registers it.
     * 
     * @param  array $config Config for the setting.
     * @param  array|null $fieldConfig Optional config for the setting's associated field.
     * 
     * @return Setting The created Setting instance.
     */
    protected function makeSetting(string $type = '', string $optionGroup = '', string $optionName = ''): Setting {
        
        if ($optionName !== '') {
            $existing = $this->getSetting($optionName);
        } else {
            $existing = null;
        }

        if ($existing !== null) {
            return $existing;
        }

        return app(
            Setting::class, [
                'source'      => $this,
                'type'        => $type,
                'optionGroup' => $optionGroup,
                'optionName'  => $optionName
            ]
        );
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
     * Returns a specific admin page object registered by the item.
     *
     * @param  string $slug The slug of the admin page to return.
     * 
     * @return AdminPage|null
     */
    final public function getAdminPage(string $slug): AdminPage|null {
        $page = $this->getSettingsPages()->firstWhere('slug', $slug);

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
     * @param  string $optionName The option name of the setting to return.
     * 
     * @return Setting|null
     */
    final public function getSetting(string $optionName): Setting|null {
        $setting = $this->getSettings()->firstWhere('optionName', $optionName);

        return $setting ?: null;
    }

    /**
     * Returns the value of a specific setting registered by the item.
     *
     * @param  string $optionName The option name of the setting to return the value of.
     * @param  mixed  $default The default value to return if the setting isn't found or doesn't have a value.
     * 
     * @return mixed
     */
    final public function getSettingValue(string $optionName): mixed {
        $setting = $this->getSetting($optionName);
        return $setting ? $setting->getValue() : null;
    }
}