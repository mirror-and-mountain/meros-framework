<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\Admin\Setting;
use MM\Meros\Services\Contracts\Admin\MenuPage;
use MM\Meros\Services\Contracts\Admin\SettingsSection;
use MM\Meros\Services\Contracts\Admin\MenuPageTemplate;

use MM\Meros\Facades\Settings;
use MM\Meros\Facades\MenuPages;
use MM\Meros\Facades\SettingsSections;
use MM\Meros\Facades\MenuPageTemplates;

use MM\Meros\Services\Registers\MenuPages as MenuPagesRegister;
use MM\Meros\Services\Registers\SettingsSections as SettingsSectionsRegister;
use MM\Meros\Services\Registers\MenuPageTemplates as MenuPageTemplatesRegister;

use MM\Meros\App\Theme;

trait HasSettings {

    /**
     * The handle for the root setting associated with this feature provider.
     *
     * @var string
     */
    private string $settingsHandle = '';

    /**
     * The root setting instance for this feature provider.
     *
     * @var Setting|null
     */
    private ?Setting $rootSetting = null;

    /**
     * Retrieves the root setting for this feature provider, or a specific sub-setting if a name is provided.
     *
     * @param string $name
     *
     * @return Setting|null
     */
    protected function settings(string $name = ''): Setting|null {
        $rootSetting = $this->rootSetting;

        if (!$rootSetting) {
            $this->setRootSetting();
        }

        if (!empty($name)) {
            return collect($this->rootSetting->subItems)->firstWhere('name', $name);
        } 
        
        else {
            return $this->rootSetting;
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
     * Retrieves a menu page template or collection of menu page templates associated with this feature provider.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return MenuPageTemplate|MenuPageTemplatesRegister|null
     */
    protected function menuPageTemplates(string $handle = '', ?Closure $callback = null): MenuPageTemplate|MenuPageTemplatesRegister|null {
        if (empty($handle)) {
            return MenuPageTemplates::checkout($this); // return register instance
        }

        else {
            return MenuPageTemplates::checkout($this)->get($handle, $callback); // return specific menu page template
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
     * Retrieves a menu page template or collection of menu page templates associated with this feature provider.
     * Alias of menuPageTemplates() for users who prefer snake_case method names.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return MenuPageTemplate|MenuPageTemplatesRegister|null
     */
    protected function menu_page_templates(string $handle = '', ?Closure $callback = null): MenuPageTemplate|MenuPageTemplatesRegister|null {
        return $this->menuPageTemplates($handle, $callback);
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
     * Sets the root setting for this feature provider if it doesn't already exist. The root setting is automatically created when the 
     * feature provider is instantiated, so this method should never need to be called manually.
     *
     * @return void
     */
    private function setRootSetting(): void {
        if (empty($this->settingsHandle)) {
            $this->settingsHandle = $this instanceof Theme
                ? 'meros_theme_settings'
                :  $this->getHandle() . '_settings';
        }

        $rootSetting = $this->rootSetting;

        if (!$rootSetting) {
            $rootSetting = $this->createRootSetting();
            $this->rootSetting = $rootSetting;
        }
    }

    /**
     * Creates the root setting for this feature provider and adds it to the registry.
     *
     * @return Setting The newly created root setting instance for the item.
     */
    private function createRootSetting(): Setting {
        $label = $this instanceof Theme
            ? 'Theme Settings'
            : $this->getName() . ' Settings';

        $setting = Settings::checkout($this)->make([
            'group' => $this->settingsHandle . '_group',
            'name'  => $this->settingsHandle,
        ])
            ->type('object')
            ->label($label);

        return $setting;
    }

    /**
     * Returns whether this feature provider has any settings.
     *
     * @return boolean
     */
    final public function hasSettings(): bool {
        return $this->settings()->hasSubItems();
    }
}