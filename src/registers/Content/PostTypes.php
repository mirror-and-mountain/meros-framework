<?php 

namespace MM\Meros\Registers\Content;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Content\PostType;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Content\PostTypes as PostTypesFacade;

class PostTypes extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->contract(PostType::class);
        $this->facade(PostTypesFacade::class);
    }
}