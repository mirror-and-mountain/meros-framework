<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Collection;

use MM\Meros\App\Settings\Setting;
use MM\Meros\App\Settings\AdminPage;
use MM\Meros\App\Settings\SettingsSection;

trait HasSettings {
    /**
     * The item's consolidated setting instance.
     *
     * @var Setting
     */
    private ?Setting $settingDefinition = null;

    /**
     * Creates (or retrieves) a root settings object for the item and returns a builder.
     *
     * This enforces a single root object like:
     *   theme_settings or package_settings
     *
     * @param callable|null $callback Optional callback to define settings.
     *
     * @return void
     */
    protected function settings(callable|\Closure|null $callback = null): void {
        // If already defined, just reuse it
        if ($this->settingDefinition instanceof Setting) {
            if ($callback) {
                $callback($this->settingDefinition->define());
            }
            return;
        }

        $optionGroup = $this->handle . '_settings';
        $optionName  = $this->handle . '_settings';

        $setting = app(Setting::class, [
            'source'      => $this,
            'optionGroup' => $optionGroup,
        ])->object($optionName);

        $this->settingDefinition = $setting;

        if ($callback) {
            $callback($setting->define());
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
    final public function getSettings(bool $readyOnly = false) {
        return $this->settingDefinition;
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