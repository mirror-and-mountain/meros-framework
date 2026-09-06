<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;

final class MerosBlocksSettings extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-blocks-settings');
        $this->title('Block Settings');
        $this->menuTitle('Block Settings');
        $this->area('theme');
    }
}