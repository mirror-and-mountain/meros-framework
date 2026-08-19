<?php 

namespace MM\Meros\Contracts\Features\Concerns;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Facades\Admin\SettingsContainers as SettingsContainersFacade;

trait IsSwitchable {
    /**
     * Whether the item is switchable (can be enabled/disabled in WP Admin).
     *
     * @var bool
     */
    private bool $isSwitchable = false;

    /**
     * The settings container that holds the switch setting for this feature, if it is switchable.
     *
     * @var SettingsContainer|null
     */
    private ?SettingsContainer $switchSettingContainer = null;

    /**
     * The name of the switch setting for this feature, if it is switchable.
     *
     * @var string
     */
    private string $switchSettingName = '';

    /**
     * Resolves the provider of the feature, used to access the provider's settings and preferences.
     *
     * @return FeatureProvider
     */
    abstract public function getProvider(): FeatureProvider;

    /**
     * Resolves the unique identifier for the feature, used for naming the switch setting.
     * 
     * @param string $format The format of the identifier to return. Can be 'default', 'slug', or 'snake'. Defaults to 'default'.
     *
     * @return string
     */
    abstract public function getIdentifier(string $format = 'default'): string;

    /**
     * Resolves the settings container for the feature to register its switch setting.
     *
     * @param SettingsContainers $register
     *
     * @return SettingsContainer
     */
    abstract protected function resolveSettingsContainer(SettingsContainers $register): SettingsContainer;

    /**
     * Runs when the feature is configured, registering the switch setting if applicable.
     * 
     * Calls the runWhenEnabled method if the feature is switchable and enabled.
     *
     * @return void
     */
    final protected function whenConfigured(): void {
        $this->beforeSwitchInit();

        if ($this->isSwitchable && !empty($this->getIdentifier())) {
            $this->registerSwitchSetting();

            add_action('meros_framework_booted', function () {
                if ($this->switchSettingContainer === null) {
                    return;
                }

                if (empty($this->switchSettingName)) {
                    return;
                }

                $container = $this->switchSettingContainer;
                $enabled   = $container->getItemValue($this->switchSettingName, true);

                if ($enabled) {
                    $this->runWhenEnabled();
                } else {
                    $this->runWhenDisabled();
                }
            });
        } else {
            $this->runWhenNotSwitchable();
        }
    }

    /**
     * Runs before the switch setting is initialised, allowing for any necessary setup or configuration.
     *
     * @return void
     */
    protected function beforeSwitchInit(): void {
        // This method can be overridden by implementing classes to perform any necessary actions before the switch setting is initialised.
    }

    /**
     * Runs the feature's functionality when it is enabled. 
     * 
     * This method should be implemented by subclasses to 
     * define the feature's behavior when enabled.
     *
     * @return void
     */
    abstract protected function runWhenEnabled(): void;

    /**
     * Runs the feature's functionality when it is disabled. 
     * 
     * This method can be overridden by subclasses to 
     * define the feature's behavior when disabled.
     *
     * @return void
     */
    protected function runWhenDisabled(): void {
        // This method can be overridden by implementing classes to define behavior when the feature is disabled.
    }

    /**
     * Runs the feature's functionality when it is not switchable. 
     * 
     * This method can be overridden by subclasses to 
     * define the feature's behavior when it is not switchable.
     * 
     * By default, this method calls the runWhenEnabled method, as the 
     * feature is always considered enabled when it is not switchable.
     *
     * @return void
     */
    protected function runWhenNotSwitchable(): void {
        $this->runWhenEnabled();
    }

    /**
     * Registers the switch setting for the feature if it is switchable.
     *
     * @return void
     */
    private function registerSwitchSetting(): void {
        if (!$this->isSwitchable) {
            return;
        }

        $container = $this->resolveSettingsContainer(
            SettingsContainersFacade::instance($this->getProvider())
        );

        $container->add('boolean', function ($setting) {
            $providerHandle = $this->getProvider()->getHandle();
            $name           = Str::replace('-', '_', $this->getIdentifier());
            $label          = Str::headline($name);

            // Store the switch setting name for later use
            $this->switchSettingName = "{$providerHandle}_{$name}_enabled";

            $setting->name($this->switchSettingName);
            $setting->label($label);
            $setting->description("Enable/Disable {$label}");
            $setting->default(true);
            $setting->field('checkbox');
        });

        // Store the switch setting container for later use
        $this->switchSettingContainer = $container;
    }

    /**
     * Sets the feature as switchable.
     *
     * @param boolean $switchable
     *
     * @return void
     */
    final protected function isSwitchable(bool $switchable = true): void {
        $this->isSwitchable = $switchable;
    }
}