<?php 

namespace MM\Meros\Registers\Admin;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Admin\Setting;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Admin\Settings as SettingsFacade;

class Settings extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->private(true);
        $this->unique(true);
        $this->definition(Setting::class);
        $this->facade(SettingsFacade::class);
    }
}