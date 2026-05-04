<?php

namespace MM\Meros\App\Admin\SettingsSections;

use MM\Meros\Services\Contracts\Admin\SettingsSection;

class Blocks extends SettingsSection {
    public string    $id    = 'meros-blocks';
    protected string $title = 'Blocks';
    protected string $page  = 'meros-features-blocks';

    public function render(): void {
        echo '';
    }
}