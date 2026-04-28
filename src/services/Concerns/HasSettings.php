<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Admin\Setting;
use MM\Meros\Services\Admin\MenuPage;
use MM\Meros\Services\Admin\SettingsSection;

use MM\Meros\Facades\Settings;
use MM\Meros\Facades\MenuPages;
use MM\Meros\Facades\SettingsSections;

use MM\Meros\Services\Registers\MenuPages as MenuPagesRegister;
use MM\Meros\Services\Registers\SettingsSections as SettingsSectionsRegister;

trait HasSettings {

    /**
     * The handle for the root setting associated with this feature provider.
     *
     * @var string
     */
    private string $settingsHandle = '';

    /**
     * Retrieves the root setting for this feature provider, or a specific sub-setting if a name is provided.
     *
     * @param string $name
     *
     * @return Setting|null
     */
    protected function settings(string $name = ''): Setting|null {
        if (empty($this->settingsHandle)) {
            $this->settingsHandle = Str::snake($this->name) . '_settings';
        }

        $rootSetting = Settings::checkout($this)->get($this->settingsHandle);

        if (!$rootSetting) {
            $rootSetting = $this->createRootSetting();
        }

        if (!empty($name)) {
            return collect($rootSetting->subItems)->firstWhere('name', $name);
        } 
        
        else {
            return $rootSetting;
        }
    }

    /**
     * Retrieves a menu page or collection of menu pages associated with this feature provider.
     *
     * @param string       $slug
     * @param Closure|null $callback
     *
     * @return MenuPage|MenuPagesRegister|null
     */
    protected function menuPages(string $slug = '', ?Closure $callback = null): MenuPage|MenuPagesRegister|null {
        if (empty($slug)) {
            return MenuPages::checkout($this); // return register instance
        }

        else {
            return MenuPages::checkout($this)->get($slug, $callback); // return specific menu page
        }
    }

    /**
     * Retrieves a menu page or collection of menu pages associated with this feature provider.
     * Alias of menuPages() for users who prefer snake_case method names.
     *
     * @param string       $slug
     * @param Closure|null $callback
     *
     * @return MenuPage|MenuPagesRegister|null
     */
    protected function menu_pages(string $slug = '', ?Closure $callback = null): MenuPage|MenuPagesRegister|null {
        return $this->menuPages($slug, $callback);
    }

    /**
     * Retrieves a settings section or collection of settings sections associated with this feature provider.
     *
     * @param string       $id
     * @param Closure|null $callback
     *
     * @return SettingsSection|SettingsSectionsRegister|null
     */
    protected function settingsSections(string $id = '', ?Closure $callback = null): SettingsSection|SettingsSectionsRegister|null {
        if (empty($id)) {
            return SettingsSections::checkout($this); // return register instance
        }

        else {
            return SettingsSections::checkout($this)->get($id, $callback); // return specific settings section
        }
    }

    /**
     * Retrieves a settings section or collection of settings sections associated with this feature provider.
     * Alias of settingsSections() for users who prefer snake_case method names.
     *
     * @param string       $id
     * @param Closure|null $callback
     *
     * @return SettingsSection|SettingsSectionsRegister|null
     */
    protected function settings_sections(string $id = '', ?Closure $callback = null): SettingsSection|SettingsSectionsRegister|null {
        return $this->settingsSections($id, $callback);
    }

    /**
     * Creates the root setting for this feature provider and adds it to the registry.
     *
     * @return Setting The newly created root setting instance for the item.
     */
    private function createRootSetting(): Setting {
        $setting = Settings::checkout($this)->make([
            'group' => $this->settingsHandle,
            'name'  => $this->settingsHandle,
        ])->type('object')->label($this->name . ' Settings');

        return $setting;
    }
}