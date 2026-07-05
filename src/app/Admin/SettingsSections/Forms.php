<?php

namespace MM\Meros\App\Admin\SettingsSections;

use MM\Meros\Services\Contracts\Admin\SettingsSection;

class Forms extends SettingsSection {
    public string    $id    = 'meros-forms';
    protected string $title = 'Forms';
    protected string $page  = 'meros-features-forms';

    public function render(): void {
        echo '
            <p>Forms are modular add-ons that extend the functionality of your Meros-powered site.<br>
            You can toggle individual forms on or off, change their settings, and manage any installable features they provide.</p>
            ';
    }
}