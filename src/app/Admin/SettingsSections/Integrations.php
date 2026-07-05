<?php

namespace MM\Meros\App\Admin\SettingsSections;

use MM\Meros\Services\Contracts\Admin\SettingsSection;

class Integrations extends SettingsSection {
    public string $id = 'meros-integrations';

    protected string $title = 'Integrations';

    protected string $page = 'meros-features-integrations';

    public function render(): void {
        echo '<p>Manage integrations registered by the framework, theme, or packages. Toggle each integration on or off and configure its credentials below.</p>';
    }
}