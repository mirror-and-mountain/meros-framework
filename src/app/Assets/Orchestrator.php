<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Orchestrators\AssetsOrchestrator;

class Orchestrator extends AssetsOrchestrator {

    protected function configure(): void {
        $this->assetGroups()->makeFrom(MFormsDeps::class, 'meros_forms_dependencies');
        $this->assetGroups()->makeFrom(MForms::class, 'meros_forms_assets');
        $this->assetGroups()->makeFrom(Admin::class, 'meros_admin_assets');
    }
}