<?php

namespace MM\Meros\Contracts\Orchestrators;

use MM\Meros\Contracts\Orchestrator;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Providers\Concerns\ProvidesSettings;

class SettingsOrchestrator extends Orchestrator {
    use ProvidesSettings;

    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        return $this->getProvider()->resolveSettingsContainer($register);
    }
}