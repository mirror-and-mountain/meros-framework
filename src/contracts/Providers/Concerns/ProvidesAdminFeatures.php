<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Admin\MenuPage;
use MM\Meros\Contracts\Features\Admin\SettingsSection;

use MM\Meros\Registers\Admin\MenuPages;
use MM\Meros\Registers\Admin\SettingsSections;

trait ProvidesAdminFeatures {
    use Abstracts, ProvidesSettings;

    /**
     * Retrieves a specific menu page by handle or returns the menu pages register.
     *
     * @param string $slug Optional. The slug of the menu page to retrieve.
     * 
     * @return MenuPage|MenuPages|null The requested menu page or the menu pages register.
     */
    final protected function menuPages(string $slug = ''): MenuPage|MenuPages|null {
        return $this->resolveRequestFor(MenuPage::class, $slug);
    }

    /**
     * Retrieves a specific settings section by name or returns the settings sections register.
     *
     * @param string $id Optional. The ID of the settings section to retrieve.
     * 
     * @return SettingsSection|SettingsSections|null The requested settings section or the settings sections register.
     */
    final protected function settingsSections(string $id = ''): SettingsSection|SettingsSections|null {
        return $this->resolveRequestFor(SettingsSection::class, $id);
    }
}
