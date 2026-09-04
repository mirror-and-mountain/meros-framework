<?php 

namespace MM\Meros\Registers\Components;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\Block;

use MM\Meros\Contracts\Registers\Maker;
use MM\Meros\Contracts\Registers\Concerns\MakesFeatures;

use MM\Meros\Facades\Components\Blocks as BlocksFacade;

class Blocks extends Register implements Maker {
    use MakesFeatures;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->contract(Block::class);
        $this->facade(BlocksFacade::class);
    }
}