<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\MenuPage;

final class ThemeSettings extends MenuPage {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $this->slug('meros-theme-settings');
        $this->title('Theme Settings');
        $this->menuTitle('Theme Settings');
        $this->area('options');
    }
}