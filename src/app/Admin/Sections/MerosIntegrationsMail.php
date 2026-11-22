<?php

namespace MM\Meros\App\Admin\Sections;

use MM\Meros\Contracts\Features\Admin\SettingsSection;

class MerosIntegrationsMail extends SettingsSection {
    protected function configure(): void {
        $this->id('meros-integrations-mail-section');
        $this->title('Mail Integrations');
        $this->description('Configure and manage your mail integration settings.');
    }
}