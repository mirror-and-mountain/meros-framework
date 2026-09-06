<?php 

namespace MM\Meros\Registers\Components;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\Block;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Components\Blocks as BlocksFacade;

class Blocks extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->contract(Block::class);
        $this->facade(BlocksFacade::class);
    }
}