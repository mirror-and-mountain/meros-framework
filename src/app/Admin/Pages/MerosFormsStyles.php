<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;

final class MerosFormsStyles extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-forms-styles');
        $this->title('Forms');
        $this->menuTitle('Forms');
        $this->area('theme');
    }
}