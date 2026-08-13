<?php

namespace MM\Meros\App\Admin\SettingsContainers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

class AssetGroupSettings extends SettingsContainer {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->name('meros_asset_group_settings');
        $this->label('Meros Asset Group Settings');
        $this->description('Toggles for asset groups registered via the Meros framework. ');
        $this->page('meros-assets');
    }
}