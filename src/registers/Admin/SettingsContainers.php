<?php 

namespace MM\Meros\Registers\Admin;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Admin\SettingsContainers as SettingsContainersFacade;

class SettingsContainers extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->definition(SettingsContainer::class);
        $this->facade(SettingsContainersFacade::class);
    }
}