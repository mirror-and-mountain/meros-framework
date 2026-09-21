<?php 

namespace MM\Meros\Registers\Components;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\DynamicBlock;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Components\DynamicBlocks as DynamicBlocksFacade;

class DynamicBlocks extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->contract(DynamicBlock::class);
        $this->facade(DynamicBlocksFacade::class);
    }
}