<?php 

namespace MM\Meros\App\Concerns;

use Closure;
use Illuminate\Support\Collection;

use MM\Meros\App\Settings\Setting;
use MM\Meros\App\Settings\AdminPage;
use MM\Meros\App\Settings\SettingsSection;

use MM\Meros\App\Support\DataBuilder;

trait HasSettings {
    /**
     * Creates or retrieves the root setting for this feature provider and allows for optional configuration via a callback.
     *
     * @param Closure|string|null $callbackOrSetting Optional callback to configure the root setting or the option name of an existing setting to retrieve.
     *
     * @return Setting The root setting instance for the item.
     */
    protected function settings(Closure|string|null $callbackOrSetting = null): Setting {
        if (is_string($callbackOrSetting)) {
            $setting = $this->registry()->get('settings')->firstWhere('option_name', $callbackOrSetting);

            if ($setting) {
                return $setting;
            }
        }

        else {
            $callback = $callbackOrSetting;
        }

        $rootSetting = $this->registry()->get('settings')->firstWhere('isProviderSetting', true);

        if ($rootSetting === null) {
            return $this->createRootSetting($callback);
        }

        if ($callback) {
            $callback($rootSetting->configure());
            return $rootSetting;
        }

        return $rootSetting;
    }

    /**
     * Creates the root setting for this feature provider and adds it to the registry.
     *
     * @param Closure|null $callback Optional callback to configure the root setting.
     *
     * @return Setting The newly created root setting instance for the item.
     */
    private function createRootSetting(Closure|null $callback = null): Setting {
        $optionGroup = $this->handle . '_settings';
        $optionName  = $this->handle . '_settings';

        $setting = app(Setting::class, [
            'source'            => $this,
            'optionGroup'       => 'general',
            'isProviderSetting' => true,
        ])->object($optionName);

        if ($callback) {
            $callback($setting->configure());
        }

        return $this->registry()->add('settings', $setting);
    }
}