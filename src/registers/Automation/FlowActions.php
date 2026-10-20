<?php 

namespace MM\Meros\Registers\Automation;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Automation\FlowAction;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Automation\FlowActions as FlowActionsFacade;

class FlowActions extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->contract(FlowAction::class);
        $this->unique(true);
        $this->facade(FlowActionsFacade::class);
        $this->identifierFormat('snake');
    }
}