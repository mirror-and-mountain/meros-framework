<?php

namespace MM\Meros\App\Admin\Settings\Containers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

class FrameworkSettings extends SettingsContainer {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->name('meros_framework_settings');
        $this->label('Meros Framework Settings');
        $this->description('Settings for the Meros Framework.');
    }
}