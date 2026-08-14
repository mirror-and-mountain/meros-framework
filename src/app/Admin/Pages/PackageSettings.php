<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\MenuPage;

final class PackageSettings extends MenuPage {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-packages');
        $this->title('Package Settings');
        $this->menuTitle('Package Settings');
        $this->area('options');
        $this->subpageParam('package');
    }
}