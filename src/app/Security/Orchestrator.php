<?php

namespace MM\Meros\App\Security;

use MM\Meros\App\Security\Capabilities\EditFormResponses;
use MM\Meros\App\Security\Capabilities\EditForms;
use MM\Meros\App\Security\Capabilities\ReadFormResponses;
use MM\Meros\App\Security\Capabilities\ReadForms;
use MM\Meros\App\Security\Roles\FormManager;
use MM\Meros\App\Security\Roles\FormReader;
use MM\Meros\Contracts\Orchestrator as BaseOrchestrator;
use MM\Meros\Contracts\Providers\Concerns\ProvidesSecurityFeatures;

class Orchestrator extends BaseOrchestrator {
    use ProvidesSecurityFeatures;

    protected function configure(): void {
        $this->capabilities(ReadForms::class)->make();
        $this->capabilities(EditForms::class)->make();
        $this->capabilities(ReadFormResponses::class)->make();
        $this->capabilities(EditFormResponses::class)->make();

        $this->roles(FormManager::class)->make();
        $this->roles(FormReader::class)->make();
    }
}
