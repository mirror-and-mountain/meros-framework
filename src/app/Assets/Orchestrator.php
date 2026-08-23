<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Orchestrators\AssetsOrchestrator;

class Orchestrator extends AssetsOrchestrator {

    protected function configure(): void {
        $this->assets()->group(MFormsDeps::class)->make()->register();
        $this->assets()->group(MForms::class)->make()->register();
        $this->assets()->group(Admin::class)->make()->enqueue();
    }
}