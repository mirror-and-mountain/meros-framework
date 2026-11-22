<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosIntegrationsGeneral extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-integrations-general-section');
        $this->title('General');
        $this->description('Manage and configure your integrations with external services.');
    }
}