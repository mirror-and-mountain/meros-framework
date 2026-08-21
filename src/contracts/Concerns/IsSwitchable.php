<?php 

namespace MM\Meros\Contracts\Concerns;

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
     * The arguments used to configure the switch.
     *
     * @var array
     */
    private array $switchSettingArgs = [];

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
    abstract public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer;

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
                    $this->whenEnabled();
                } else {
                    $this->whenDisabled();
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
    abstract protected function whenEnabled(): void;

    /**
     * Runs the feature's functionality when it is disabled. 
     * 
     * This method can be overridden by subclasses to 
     * define the feature's behavior when disabled.
     *
     * @return void
     */
    protected function whenDisabled(): void {
        // This method can be overridden by implementing classes to define behavior when the feature is disabled.
    }

    /**
     * Runs the feature's functionality when it is not switchable. 
     * 
     * This method can be overridden by subclasses to 
     * define the feature's behavior when it is not switchable.
     * 
     * By default, this method calls the whenEnabled method, as the 
     * feature is always considered enabled when it is not switchable.
     *
     * @return void
     */
    protected function runWhenNotSwitchable(): void {
        $this->whenEnabled();
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

        $args = $this->switchSettingArgs;
        $label       = $args['label'] ?? '';
        $description = $args['description'] ?? '';
        $default     = $args['default'] ?? true;
        $name        = $args['name'] ?? '';

        $customName = $name !== '';

        $providerHandle = $this->getProvider()->getHandle();
        $label          = $label ?: Str::headline(Str::replace('-', '_', $this->getIdentifier()));
        $name           = $name ?: Str::replace('-', '_', $this->getIdentifier());
        $description    = $description ?: "Enable/Disable {$label}";

        if ($customName) {
            $this->switchSettingName = $name;
        } else {
            $this->switchSettingName = "{$providerHandle}_{$name}_enabled";
        }


        $container->add('boolean', function ($setting) use ($label, $description, $default) {
            $setting->name($this->switchSettingName);
            $setting->label($label);
            $setting->description($description);
            $setting->default($default);
            $setting->field('checkbox');
        });

        // Store the switch setting container for later use
        $this->switchSettingContainer = $container;
    }

    /**
     * Configures the feature as switchable, allowing it to be enabled or disabled in WP Admin.
     *
     * @param string $label       The label for the switch setting.
     * @param string $description Optional. The description for the switch setting. Defaults to an empty string.
     * @param bool   $default     Optional. The default value for the switch setting. Defaults to true (enabled).
     * @param string $name        Optional. The name for the switch setting. If not provided, a name will be generated based on the feature's identifier.
     *
     * @return void
     */
    final protected function switch(string $label, string $description = '', bool $default = true, string $name = ''): void {
        $this->isSwitchable(true);
        $this->switchSettingArgs = [
            'label'       => $label,
            'description' => $description,
            'default'     => $default,
            'name'        => $name,
        ];
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