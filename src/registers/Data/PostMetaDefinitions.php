<?php 

namespace MM\Meros\Registers\Data;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Data\PostMeta;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Data\PostMetaDefinitions as PostMetaDefinitionsFacade;

class PostMetaDefinitions extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->definition(PostMeta::class);
        $this->facade(PostMetaDefinitionsFacade::class);
    }
}