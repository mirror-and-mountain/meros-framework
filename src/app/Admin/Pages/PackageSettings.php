<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;

final class PackageSettings extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-packages');
        $this->title('Package Settings');
        $this->menuTitle('Package Settings');
        $this->area('options');
        $this->position(0);
        $this->subpageParam('package');
    }
}