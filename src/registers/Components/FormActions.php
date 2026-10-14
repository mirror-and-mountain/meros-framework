<?php 

namespace MM\Meros\Registers\Components;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\FormAction;

use MM\Meros\Contracts\Registers\Registrar;
use MM\Meros\Contracts\Registers\Concerns\RegistersFeatures;

use MM\Meros\Facades\Components\FormActions as FormActionsFacade;

class FormActions extends Register implements Registrar {
    use RegistersFeatures;

    protected function configure(): void {
        $this->contract(FormAction::class);
        $this->unique(true);
        $this->facade(FormActionsFacade::class);
        $this->identifierFormat('snake');
    }
}