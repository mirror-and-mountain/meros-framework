<?php

namespace MM\Meros\App\Admin\Auth;

use MM\Meros\Contracts\Orchestrators\SettingsOrchestrator;

class Orchestrator extends SettingsOrchestrator {
    protected function configure(): void {
        $mfaEnabled = $this->getSettingValue('meros_security_mfa_enabled');
    }
}