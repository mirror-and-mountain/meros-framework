<?php

namespace MM\Meros\App\Admin\Settings\Containers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

final class PackageSettings extends SettingsContainer {
    
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