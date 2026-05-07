<?php

namespace MM\Meros\App\Admin\SettingsSections;

use MM\Meros\Services\Contracts\Admin\SettingsSection;

class Assets extends SettingsSection {
    public string    $id    = 'meros-assets';
    protected string $title = 'Scripts & Styles';
    protected string $page  = 'meros-features-assets';

    public function render(): void {
        echo '<p>Manage scripts and styles registered by the theme or packages.</p>';
    }
}