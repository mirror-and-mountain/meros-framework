<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Admin\Setting;
use MM\Meros\Services\Contracts\Admin\MenuPage;
use MM\Meros\Services\Contracts\Admin\SettingsSection;
use MM\Meros\Services\Contracts\Admin\MenuPageTemplate;
use MM\Meros\Services\Contracts\FeatureSet;

use MM\Meros\Facades\Settings;
use MM\Meros\Facades\MenuPages;
use MM\Meros\Facades\SettingsSections;
use MM\Meros\Facades\MenuPageTemplates;
use MM\Meros\Facades\FeatureSets;

use MM\Meros\Services\Registers\MenuPages as MenuPagesRegister;
use MM\Meros\Services\Registers\SettingsSections as SettingsSectionsRegister;
use MM\Meros\Services\Registers\MenuPageTemplates as MenuPageTemplatesRegister;

use MM\Meros\App\BaseTheme;
use MM\Meros\App\Package;
use MM\Meros\App\Framework;

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
     * The current values of all settings registered by the feature provider.
     *
     * @var array
     */
    protected array $settingsValues;

    /**
     * Indicates whether this feature provider has any settings registered.
     *
     * @var boolean
     */
    protected bool $hasSettings;

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
            $container = $this->getDefaultSettingsContainer();
            $this->currentSettingsContainer = 'default';
        }

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
            return MenuPages::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return MenuPages::get($slug, $this->resolveAuthority(), $callback); // return specific menu page
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
            return MenuPageTemplates::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return MenuPageTemplates::get($handle, $this->resolveAuthority(), $callback); // return specific menu page template
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
            return SettingsSections::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return SettingsSections::get($id, $this->resolveAuthority(), $callback); // return specific settings section
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
            $container = Settings::checkout($this->resolveAuthority())->make([
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

        $authority = $this->resolveAuthority();
        $name      = $authority->getHandle() . '_settings';
        $container = null;

        if ($authority instanceof BaseTheme) {
            $container = Settings::get('meros_theme_settings')->setProvider($authority);
        }

        else if ($authority instanceof Package) {
            $container = Settings::get('meros_package_settings')
                ->add()
                ->setProvider($authority)
                ->object($name)
                ->label($authority->getName() . ' Settings');
        }

        else if ($authority instanceof Framework) {
            $container = Settings::checkout($authority)->make([
                'group' => 'meros_framework_settings',
                'name'  => 'meros_framework_settings',
            ])
                ->type('object')
                ->label('Meros Framework Settings');
        }

        if ($container === null) {
            throw new \BadMethodCallException("The default settings container for the feature provider '{$authority->getHandle()}' has not been created.");
        }

        $this->settingsContainers['default'] = $container;
        return $container;
    }

    /**
     * Returns whether this feature provider has any settings.
     *
     * @return boolean
     */
    final public function hasSettings(): bool {
        if (isset($this->hasSettings)) {
            return $this->hasSettings;
        }

        $hasSettings = false;

        foreach ($this->settingsContainers as $container) {
            if ($container instanceof Setting && !empty($container->getSubItems())) {
                $hasSettings = true;
                break;
            }
        }
        
        $this->hasSettings = $hasSettings;
        return $this->hasSettings;
    }

    /**
     * Returns the slug of the settings page for this item.
     *
     * @return string
     */
    final public function getSettingsPageSlug(): string {
        return $this instanceof BaseTheme
            ? 'meros-theme-settings' 
            : 'meros-packages-' . $this->getHandle();
    }

    /**
     * Retrieves the current values of all settings registered by the feature provider.
     * 
     * @param string|bool $container The name of the settings container to retrieve values from, or a boolean indicating whether to refresh the cached values.
     * @param bool        $refresh   Whether to refresh the cached values of the settings.
     *
     * @return array
     */
    public function getSettings(string|bool $container = '', bool $refresh = false): array {
        if (is_bool($container)) {
            $refresh = $container;
            $container = '';
        }

        if (!isset($this->settingsValues) || $refresh) {
            $value = [];
            foreach ($this->settingsContainers as $settingContainer) {
                if ($settingContainer instanceof Setting) {
                    $value[$settingContainer->name] = $settingContainer->getValue();
                }
            }

            if ($this instanceof FeatureSet === false) {
                $featureSets = FeatureSets::all($this->resolveAuthority());

                if ($featureSets instanceof Collection) {
                    foreach ($featureSets as $featureSet) {
                        if ($featureSet instanceof FeatureSet) {
                            $value[$featureSet->handle] = $featureSet->getSettings($refresh);
                        }
                    }
                }
            }

            $this->settingsValues = $value;
        }

        if (!empty($container) && $this->settingsContainers[$container] ?? null instanceof Setting) {
            return $this->settingsValues[$container] ?? [];
        }

        return $this->settingsValues;
    }

    /**
     * Retrieves the current values of all settings registered by the feature provider.
     * Alias of getSettings() for users who prefer snake_case method names.
     *
     * @param string|bool $container The name of the settings container to retrieve values from, or a boolean indicating whether to refresh the cached values.
     * @param bool        $refresh   Whether to refresh the cached values of the settings.
     *
     * @return array
     */
    public function get_settings(string $container = '', bool $refresh = false): array {
        return $this->getSettings($container, $refresh);
    }
}