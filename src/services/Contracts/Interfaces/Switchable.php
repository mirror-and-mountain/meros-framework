<?php 

namespace MM\Meros\Services\Contracts\Interfaces;

interface Switchable {
    /**
     * Sets the name of the setting that controls whether the item is enabled.
     *
     * @param string $settingName
     *
     * @return self
     */
    public function setEnabledSetting(string $settingName): self;

    /**
     * Sets whether the item is switchable.
     *
     * @param bool $switchable
     *
     * @return self
     */
    public function switchable(bool $switchable): self;

    /**
     * Checks whether the item is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Sets the items that this item depends on.
     *
     * @param string|array $items
     *
     * @return self
     */
    public function dependsOn(string|array $items): self;

    /**
     * Gets the items that this item depends on.
     *
     * @return array<string>
     */
    public function getDependsOn(): array;
}