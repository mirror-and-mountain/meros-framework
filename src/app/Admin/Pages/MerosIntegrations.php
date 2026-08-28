<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;

final class MerosIntegrations extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-integrations');
        $this->title('Integrations');
        $this->menuTitle('Integrations');
        $this->area('options');
        $this->position(1);
        $this->subpageParam('integration');
    }
}