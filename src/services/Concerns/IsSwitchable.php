<?php 

namespace MM\Meros\Services\Concerns;

use Illuminate\Support\Str;

trait IsSwitchable {
    /**
     * Whether the item is switchable (can be enabled/disabled in WP Admin).
     *
     * @var bool
     */
    protected bool $isSwitchable = false;

    /**
     * Whether the item is enabled.
     *
     * @var bool
     */
    protected bool $isEnabled = true;

    /**
     * The name of the setting that controls whether the item is enabled.
     *
     * @var string
     */
    protected string $enabledSetting = '';

    /**
     * The WordPress hook that the item uses to register itself.
     *
     * @var string
     */
    protected string $hook = '';

    /**
     * Sets the name of the setting that controls whether the item is enabled.
     *
     * @param string $settingName
     *
     * @return void
     */
    final public function setEnabledSetting(string $settingName): void {
        $this->enabledSetting = $settingName;
        $this->queue();
    }

    /**
     * Sets whether the item is switchable.
     *
     * @param bool $switchable
     *
     * @return void
     */
    final public function setIsSwitchable(bool $switchable): void {
        $this->isSwitchable = $switchable;
    }

    /**
     * Sets whether the item is enabled based on the corresponding setting in WP Admin or the provider's default preference.
     * Concrete classes using this trait should call this method in their queue() method to ensure the item is only hooked into WordPress if it's enabled.
     *
     * @return void
     */
    protected function setIsEnabled(): void {
        $item           = Str::plural(Str::lower(class_basename($this)));
        $preferenceName = $item . '_are_enabled_by_default';

        if ($this->isSwitchable) {
            $this->isEnabled = get_option('meros_framework_settings')[$item][$this->enabledSetting] ?? $this->provider->getPreference($preferenceName);
        } else {
            $this->isEnabled = true;
        }
    }
}