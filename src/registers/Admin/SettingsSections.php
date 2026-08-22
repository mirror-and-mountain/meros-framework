<?php 

namespace MM\Meros\Registers\Admin;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Admin\SettingsSection;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Admin\SettingsSections as SettingsSectionsFacade;

class SettingsSections extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->unique(true);
        $this->contract(SettingsSection::class);
        $this->facade(SettingsSectionsFacade::class);
        $this->identifierFormat('slug');
    }
}