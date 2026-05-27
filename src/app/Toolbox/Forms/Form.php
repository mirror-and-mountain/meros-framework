<?php

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;

use MM\Meros\App\Models\MerosForm as FormModel;
use MM\Meros\App\Toolbox\Forms\Concerns\ManagesFormSchema;

use MM\Meros\App\Toolbox\Forms\Helpers\Hydrator;
use MM\Meros\App\Toolbox\Forms\Helpers\Serializer;
use MM\Meros\App\Toolbox\Forms\Helpers\Utilities;

class Form extends Component {
    public bool $showTitle = false;
    public bool $showDescription = true;

    use ManagesFormSchema;

    public function mount(string|int $formID, bool $showTitle = false, bool $showDescription = true) {
        $this->initialiseFields();
        $this->initialiseFieldGroups();

        $this->formID = $formID;
        $this->showTitle = $showTitle;
        $this->showDescription = $showDescription;

        if ($this->formID) {
            $this->form = FormModel::find($formID);

            if ($this->form) {
                $rawSchema = $this->loadFormSchema($this->form->schema());
                $this->schema = [
                    'rows'     => Utilities::normaliseRowPayloads($rawSchema['rows'] ?? []),
                    'settings' => $rawSchema['settings'] ?? [],
                ];

                $this->settings = $this->schema['settings'] ?? [];
                $this->rowPayloads = $this->schema['rows'] ?? [];
            }
        }
    }

    public function render() {
        $hydrator = Hydrator::make($this->fieldTypes);
        $hydratedRows = $hydrator->hydrateRowPayloads($this->rowPayloads);

        return view('meros::toolbox.forms.site-form.index', [
            'formRows' => $hydratedRows,
            'showTitle' => $this->showTitle,
            'showDescription' => $this->showDescription,
        ]);
    }
}