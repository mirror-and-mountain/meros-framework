<?php 

namespace MM\Meros\Registers\Data;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Data\Integration;

use MM\Meros\Contracts\Registers\Registrar;
use MM\Meros\Contracts\Registers\Concerns\RegistersFeatures;

use MM\Meros\Facades\Data\Integrations as IntegrationsFacade;

class Integrations extends Register implements Registrar {
    use RegistersFeatures;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->contract(Integration::class);
        $this->facade(IntegrationsFacade::class);
    }
}