<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Orchestrators\AssetsOrchestrator;

class Orchestrator extends AssetsOrchestrator {

    protected function configure(): void {
        $this->assetGroups(MFormsDeps::class)->make();
        $this->assetGroups(MForms::class)->make();
        $this->assetGroups(Admin::class)->make();
    }
}