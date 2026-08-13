<?php

namespace MM\Meros\App\Admin\SettingsContainers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

class PackageSettings extends SettingsContainer {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->name('meros_package_settings');
        $this->label('Meros Package Settings');
        $this->description('Settings provided by Meros packages.');
        $this->page('meros-packages');
    }
}