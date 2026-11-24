<?php

namespace MM\Meros\Registers\Security;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Security\Capability;
use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;
use MM\Meros\Facades\Security\Capabilities as CapabilitiesFacade;

class Capabilities extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->unique(true);
        $this->contract(Capability::class);
        $this->facade(CapabilitiesFacade::class);
        $this->identifierFormat('snake');
    }
}
