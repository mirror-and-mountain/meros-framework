<?php 

namespace MM\Meros\Registers\Components;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\Contracts\Registers\Registrar;
use MM\Meros\Contracts\Registers\Concerns\RegistersFeatures;

use MM\Meros\Facades\Components\Fields as FieldsFacade;

class Fields extends Register implements Registrar {
    use RegistersFeatures;

    protected function configure(): void {
        $this->contract(Field::class);
        $this->facade(FieldsFacade::class);
    }

    public function cloneField(Field $sourceField, string $name, string $id = ''): Field {
        $newField = clone $sourceField;
        $newField->name($name);
        
        if ($id === '') {
            $idSuffix = Str::substr(Str::uuid(), 0, 8);
            $id = "mforms-field-{$idSuffix}";
        }

        $newField->id($id);
        $this->attachInstance($newField, $newField->getProvider());

        return $newField;
    }
}