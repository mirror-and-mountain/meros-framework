<?php

namespace MM\Meros\App\Admin\Settings\Containers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

class MerosThemeSettings extends SettingsContainer {

    // =========================================================================
    // Initialisation
    // =========================================================================
    
    protected function configure(): void {
        $this->name('meros_theme_settings');
        $this->label('Meros Theme Settings');
        $this->description('Settings provided by the current Meros theme.');
        $this->page('meros-theme-settings');
    }
}