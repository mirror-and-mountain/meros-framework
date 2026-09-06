<?php

namespace MM\Meros\App\Admin\Settings\Containers;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;

class MerosBlocksSettings extends SettingsContainer {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->name('meros_blocks_settings');
        $this->label('Meros Blocks Settings');
        $this->description('Toggles for Gutenberg blocks registered via the Meros framework. ');
        $this->page('meros-blocks-settings');
    }
}