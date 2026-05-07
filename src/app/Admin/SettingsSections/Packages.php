<?php

namespace MM\Meros\App\Admin\SettingsSections;

use MM\Meros\Services\Contracts\Admin\SettingsSection;

class Packages extends SettingsSection {
    public string    $id    = 'meros-packages';
    protected string $title = 'Packages';
    protected string $page  = 'meros-features-packages';

    public function render(): void {
        echo '
            <p>Packages are modular add-ons that extend the functionality of your Meros-powered site.<br>
            You can toggle individual packages on or off, change their settings, and manage any installable features they provide.</p>
            ';
    }
}