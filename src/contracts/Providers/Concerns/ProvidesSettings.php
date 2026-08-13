<?php 

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Registers\Admin\SettingsContainers;

use MM\Meros\Facades\Admin\Settings as SettingsFacade;
use MM\Meros\Facades\Admin\SettingsContainers as SettingsContainersFacade;

trait ProvidesSettings {
    /**
     * Retrieves the settings container associated with the implementing class.
     *
     * @return SettingsContainer The settings container for the implementing class.
     */
    abstract public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer;

    /**
     * Retrieves a specific setting by name or returns the provider's settings container.
     *
     * @param string $name Optional. The name of the setting to retrieve.
     * 
     * @return Setting|SettingsContainer|null The requested setting or the provider's settings container.
     */
    protected function settings(string $name = ''): Setting|SettingsContainer|null {
        if (!empty($name)) {
            return SettingsFacade::get($name, $this);
        }

        return $this->resolveSettingsContainer(SettingsContainersFacade::instance());
    }

    /**
     * Retrieves the values of all settings associated with the implementing class.
     *
     * @param bool $refresh Optional. Whether to refresh the settings values from the source.
     * 
     * @return array The values of all settings for the implementing class.
     */
    protected function getSettingsValues(bool $refresh = false): array {
        $settingsContainer = $this->settings();

        if ($settingsContainer instanceof SettingsContainer) {
            return $settingsContainer->getValue($refresh);
        }

        return [];
    }

    /**
     * Retrieves the configuration values for the implementing class.
     * 
     * Alias for getSettingsValues().
     *
     * @param bool $refresh Optional. Whether to refresh the configuration values from the source.
     * 
     * @return array The configuration values for the implementing class.
     */
    protected function getConfiguration(bool $refresh = false): array {
        return $this->getSettingsValues($refresh);
    }

    /**
     * Retrieves the value of a specific setting by name.
     *
     * @param string $name The name of the setting to retrieve.
     * @param bool   $refresh Optional. Whether to refresh the setting value from the source.
     * 
     * @return mixed The value of the specified setting, or null if not found.
     */
    protected function getSettingValue(string $name, bool $refresh = false): mixed {
        $setting = $this->settings($name);

        if ($setting instanceof Setting) {
            return $setting->getValue($refresh);
        }

        return null;
    }

    /**
     * Retrieves the value of a specific configuration setting by name.
     * 
     * Alias for getSettingValue().
     *
     * @param string $name The name of the configuration setting to retrieve.
     * @param bool   $refresh Optional. Whether to refresh the configuration value from the source.
     * 
     * @return mixed The value of the specified configuration setting, or null if not found.
     */
    protected function getConfigurationValue(string $name, bool $refresh = false): mixed {
        return $this->getSettingValue($name, $refresh);
    }
}