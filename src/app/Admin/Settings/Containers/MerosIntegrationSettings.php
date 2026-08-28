<?php

namespace MM\Meros\App\Admin\Settings\Containers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

class MerosIntegrationSettings extends SettingsContainer {

    // =========================================================================
    // Initialisation
    // =========================================================================
    
    protected function configure(): void {
        $this->name('meros_integration_settings');
        $this->label('Meros Integration Settings');
        $this->description('Toggles for registered integrations.');
        $this->page('meros-integrations');
    }
}