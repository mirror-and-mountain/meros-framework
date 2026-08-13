<?php 

namespace MM\Meros\Registers\Admin;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Admin\MenuPage;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Admin\MenuPages as MenuPagesFacade;

class MenuPages extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->unique(true);
        $this->definition(MenuPage::class);
        $this->facade(MenuPagesFacade::class);
    }
}