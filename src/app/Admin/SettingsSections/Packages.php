<?php

namespace MM\Meros\App\Admin\SettingsSections;

use MM\Meros\Services\Contracts\Admin\SettingsSection;

class Packages extends SettingsSection {
    public string    $id    = 'meros-packages';
    protected string $title = 'Packages';
    protected string $page  = 'meros-features-packages';

    public function render(): void {
        echo '';
    }
}