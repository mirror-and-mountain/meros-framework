<?php 

namespace MM\Meros\Registers\Data;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Data\PostMetaContainer;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Data\PostMetaContainers as PostMetaContainersFacade;

class PostMetaContainers extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->contract(PostMetaContainer::class);
        $this->facade(PostMetaContainersFacade::class);
    }
}