<?php

namespace MM\Meros\App\Admin\Pages;

use MM\Meros\Contracts\Features\Admin\Page;
use MM\Meros\Facades\Theme;

final class MerosThemeSettings extends Page {
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function configure(): void {
        $theme = Theme::get();
        $title = $theme->getName() . ' Settings';

        $this->slug('meros-theme-settings');
        $this->title($title);
        $this->menuTitle($title);
        $this->area('options');
        $this->position(0);
        $this->setProvider($theme);

        $this->callback(function () use ($theme) {            
            if ($theme->hasRegisteredTables()) {
                echo '<div style="margin-bottom:2rem;">';
                echo '<h2>Custom Tables</h2>';
                echo '<p>It looks like this theme has registered custom database tables: ';
                echo '<a href="' . admin_url('admin.php?page=meros-theme-settings&tables=meros-theme-settings-tables') . '" title="Manage Custom Database Tables">Manage</a>';
                echo '</p></div>';
                echo '<h2>Settings</h2>';
            }
        });
    }
}