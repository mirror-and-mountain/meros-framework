<?php 

namespace MM\Meros\Registers\Admin;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Admin\Page;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Admin\Pages as PagesFacade;

class Pages extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->unique(true);
        $this->contract(Page::class);
        $this->facade(PagesFacade::class);
    }
}