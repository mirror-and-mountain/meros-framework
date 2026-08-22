<?php 

namespace MM\Meros\Registers\Components;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\Form;

use MM\Meros\Contracts\Registers\RegistrarMaker;
use MM\Meros\Contracts\Registers\Concerns\IsRegistrarMaker;

use MM\Meros\Facades\Components\Forms as FormsFacade;

class Forms extends Register implements RegistrarMaker {
    use IsRegistrarMaker;

    protected function configure(): void {
        $this->contract(Form::class);
        $this->unique(true);
        $this->facade(FormsFacade::class);
        $this->identifierFormat('slug');
    }
}