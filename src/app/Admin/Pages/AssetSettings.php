<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;

final class AssetSettings extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-assets');
        $this->title('Asset Settings');
        $this->menuTitle('Asset Settings');
        $this->area('theme');
    }
}