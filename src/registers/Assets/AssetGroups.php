<?php 

namespace MM\Meros\Registers\Assets;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Assets\AssetGroup;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Assets\AssetGroups as AssetGroupsFacade;

class AssetGroups extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->unique(true);
        $this->contract(AssetGroup::class);
        $this->facade(AssetGroupsFacade::class);
    }
}