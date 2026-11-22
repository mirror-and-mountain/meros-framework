<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosIntegrationsCrm extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-integrations-crm-section');
        $this->title('CRM Integrations');
        $this->description('Configure and manage your CRM integration settings.');
    }
}