<?php 

namespace MM\Meros\App\Contracts;

use Illuminate\Support\Collection;
use MM\Meros\App\Features\Settings\Setting;
use MM\Meros\App\Features\Settings\SettingsPage;
use MM\Meros\App\Features\Settings\SettingsSection;

interface SettingsRegistrar {
    /**
     * Returns a collection of setting objects.
     *
     * @param  bool $readyOnly Whether to return only settings that are ready.
     *
     * @return Collection
     */
    public function getSettings(bool $readyOnly = false): Collection;

    /**
     * Returns a specific setting object.
     * 
     * @param  string $handle The setting's handle.
      *
      * @return Setting|null
     */
    public function getSetting(string $handle): Setting|null;

    /**
     * Returns the value of a specific setting.
     * 
     * @param  string $handle The setting's handle.
     * 
     * @return mixed
     */
    public function getSettingValue(string $handle): mixed;

    /**
     * Returns a collection of settings page objects.
     *
     * @param  bool $readyOnly Whether to return only settings pages that are ready.
     *
     * @return Collection
     */
    public function getSettingsPages(bool $readyOnly = false): Collection;

    /**
     * Returns a specific settings page object.
     * 
     * @param  string $handle The settings page's handle.
      *
      * @return SettingsPage|null
     */
    public function getSettingsPage(string $handle): SettingsPage|null;

    /**
     * Returns a collection of settings section objects.
     *
     * @param  bool $readyOnly Whether to return only settings sections that are ready.
     *
     * @return Collection
     */
    public function getSettingsSections(bool $readyOnly = false): Collection;

    /**
     * Returns a specific settings section object.
     * 
     * @param  string $handle The settings section's handle.
      *
      * @return SettingsSection|null
     */
    public function getSettingsSection(string $handle): SettingsSection|null;
}