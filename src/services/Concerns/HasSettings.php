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
     * The container that the provider is currently able to access for settings.
     *
     * @var array<Setting>
     */
    protected array $settingsContainers = [];

    /**
     * The name of the current (working) settings container for this provider.
     * 
     *
     * @var string
     */
    protected string $currentSettingsContainer = '';

    /**
     * Retrieves the current settings container for this feature provider, or a specific sub-setting 
     * inside the current container if a name is provided. 
     * 
     * If the current settings container is not set, the default settings container will be used instead.
     *
     * @param string $name
     *
     * @return Setting|null
     */
    protected function settings(string $name = ''): Setting|null {
        $container = isset($this->settingsContainers[$this->currentSettingsContainer])
            ? $this->settingsContainers[$this->currentSettingsContainer]
            : null;

        if ($container === null) {
            return null;
        }

        if (!empty($name)) {
            return collect($container->getSubItems())->firstWhere('name', $name);
        } 
        
        else {
            return $container;
        }
    }

    /**
     * Retrieves a menu page if a slug is provided or the menu page register if no slug is provided.
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
     * Retrieves a menu page template if a handle is provided or the menu page template register if no handle is provided.
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
     * Retrieves a menu page if a slug is provided or the menu page register if no slug is provided.
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
     * Retrieves a menu page template if a handle is provided or the menu page template register if no handle is provided.
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
     * Retrieves a settings section if an ID is provided or the settings section register if no ID is provided.
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
     * Retrieves a settings section if an ID is provided or the settings section register if no ID is provided.
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
     * Sets the working settings container, using the 'default' container if $name isn't specified.
     * 
     * @param string $name The name of the settings container to create. If not provided, 'default' will be used.
     *
     * @return Setting The newly created default settings container instance for the item.
     */
    protected function settingsContainer(string $name = ''): Setting {
        if (empty($name) || $name === 'default') {
            $container = $this->getDefaultSettingsContainer();
            $this->currentSettingsContainer = 'default';
            return $container;
        }

        else if (!empty($name) && isset($this->settingsContainers[$name])) {
            $this->currentSettingsContainer = $name;
            return $this->settingsContainers[$name];
        }

        else {
            // Create new settings container with provided name
            $container = Settings::checkout($this)->make([
                'group' => Str::snake($name) . '_container',
                'name'  => Str::snake($name),
            ])
                ->type('object')
                ->label(Str::title(Str::replace('_', ' ', $name)) . ' Settings');

            $this->settingsContainers[$name] = $container;
            $this->currentSettingsContainer  = $name;

            return $container;
        }
    }

    /**
     * Retrieves a settings container by name, or the default settings container if no name is provided.
     * Alias of settingsContainer() for users who prefer snake_case method names.
     *
     * @param string $name
     *
     * @return Setting The settings container instance for the item.
     */
    protected function settings_container(string $name = ''): Setting {
        return $this->settingsContainer($name);
    }

    /**
      * Retrieves the default settings container for this feature provider, creating it if it doesn't already exist.
      *
      * @return Setting The default settings container instance for the item.
      */
    private function getDefaultSettingsContainer(): Setting {
        if (isset($this->settingsContainers['default'])) {
            return $this->settingsContainers['default'];
        }

        $name = $this instanceof Theme
            ? 'meros_theme_settings'
            : $this->getHandle() . '_settings';

        $label = $this instanceof Theme
            ? 'Theme Settings'
            : $this->getName() . ' Settings';

        $setting = Settings::checkout($this)->make([
            'group' => $name . '_container',
            'name'  => $name,
        ])
            ->type('object')
            ->label($label);

        $this->settingsContainers['default'] = $setting;
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