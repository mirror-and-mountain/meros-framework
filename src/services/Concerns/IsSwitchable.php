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
     * Whether the item is enabled by default.
     *
     * @var bool
     */
    protected bool $enabledByDefault = false;

    /**
     * The name of the setting that controls whether the item is enabled.
     *
     * @var string
     */
    protected string $enabledSetting = '';

    /**
     * An array of other switchable items that this item depends on.
     *
     * @var array<string>
     */
    protected array $dependsOn = [];

    /**
     * Sets the name of the setting that controls whether the item is enabled.
     *
     * @param string $settingName
     *
     * @return self
     */
    final public function setEnabledSetting(string $settingName): self {
        $this->enabledSetting = $settingName;
        $this->queue();
        return $this;
    }

    /**
     * Sets whether the item is switchable.
     *
     * @param bool $switchable
     *
     * @return self
     */
    final public function switchable(bool $switchable): self {
        $this->isSwitchable = $switchable;
        return $this;
    }

    /**
     * Sets whether the item is enabled by default.
     *
     * @param bool $enabledByDefault
     *
     * @return self
     */
    final public function enabledByDefault(bool $enabledByDefault): self {
        $this->enabledByDefault = $enabledByDefault;
        return $this;
    }

    /**
     * Sets the items that this item depends on.
     *
     * @param string|array $items
     *
     * @return self
     */
    final public function dependsOn(string|array $items): self {
        $this->dependsOn = is_array($items) ? $items : [$items];
        return $this;
    }

    /**
     * Sets whether the item is enabled based on the corresponding setting in WP Admin or the provider's default preference.
     * Concrete classes using this trait should call this method in their queue() method to ensure the item is only hooked into WordPress if it's enabled.
     *
     * @return void
     */
    protected function setIsEnabled(): void {
        if ($this->isSwitchable) {
            $item = Str::snake(Str::plural(Str::lower(Str::headline(class_basename($this)))));
            
            if ($this->dependsOn !== []) {
                $dependenciesCount    = count($this->dependsOn);
                $disabledDependencies = [];

                foreach ($this->dependsOn as $dependency) {
                    if (get_option('meros_framework_settings')[$item][$dependency] ?? $this->enabledByDefault) {
                        continue;
                    }

                    $disabledDependencies[] = $dependency;
                }

                if ($dependenciesCount === count($disabledDependencies)) {
                    $this->isEnabled = false;
                    return;
                }
            }

            $this->isEnabled = get_option('meros_framework_settings')[$item][$this->enabledSetting] ?? $this->enabledByDefault;
            return;
        }

        $this->isEnabled = true;
        return;
    }

    /**
     * Gets whether the item is enabled.
     *
     * @return bool
     */
    final public function isEnabled(): bool {
        return $this->isEnabled;
    }

    /**
     * Gets whether the item is switchable.
     *
     * @return bool
     */
    final public function isSwitchable(): bool {
        return $this->isSwitchable;
    }

    /**
     * Gets the items that this item depends on.
     *
     * @return array<string>
     */
    final public function getDependsOn(): array {
        return $this->dependsOn;
    }
}