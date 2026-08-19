<?php 

namespace MM\Meros\Registers\Components;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Components\FieldGroups as FieldGroupsFacade;

class FieldGroups extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->contract(FieldGroup::class);
        $this->unique(true);
        $this->facade(FieldGroupsFacade::class);
    }
}