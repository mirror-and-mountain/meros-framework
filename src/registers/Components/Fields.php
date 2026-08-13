<?php 

namespace MM\Meros\Registers\Components;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\Contracts\Registers\Registrar;
use MM\Meros\Contracts\Registers\Concerns\RegistersFeatures;

use MM\Meros\Facades\Components\Fields as FieldsFacade;

class Fields extends Register implements Registrar {
    use RegistersFeatures;

    protected function configure(): void {
        $this->definition(Field::class);
        $this->facade(FieldsFacade::class);
    }
}