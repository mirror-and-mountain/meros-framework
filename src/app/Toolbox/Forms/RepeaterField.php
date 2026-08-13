<?php 

namespace MM\Meros\App\Toolbox\Forms;

use Livewire\Component;
use Livewire\Attributes\Renderless;

use MM\Meros\App\Fields\Repeater;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\FieldGroups;
use MM\Meros\Facades\Fields;

class RepeaterField extends Component {
    /**
     * The ID of the repeater field associated with this component.
     *
     * @var string
     */
    public string $repeaterId = '';

    /**
     * The name of the repeater field associated with this component.
     *
     * @var string
     */
    public string $repeaterName = '';

    /**
     * Props passed by the repeater contract
     *
     * @var array
     */
    protected array $props = [];

    public Repeater $field;

    /**
     * Whether to show the repeater's configuration form.
     *
     * @var boolean
     */
    public bool $showConfigurationForm = false;

    public function mount(string $id, string $name, Repeater $field, array $props): void {
        $this->field = $field;
        $this->repeaterId   = $id;
        $this->repeaterName = $name;
        $this->props        = $props;
    }

    public function render() {
        $configFormHTML = null;

        if ($this->showConfigurationForm) {
            $configFormHTML = $this->renderRepeaterRowConfigurationForm([], '');
        }

        // $field = $this->resolveRepeater();
        // $this->props = $field->getRefreshedRenderProps($this->props);
        $this->props['field'] = $this->field;

        return view('meros::forms.field-wrappers.site-default', [
            'configFormHTML' => $configFormHTML,
            ...$this->props
        ]);
    }

    public function toggleRowDialog(): void {
        $this->showConfigurationForm = !$this->showConfigurationForm;
    }

    private function renderRepeaterRowConfigurationForm(array $rowData, string $rowToken): string {
        // $repeater = $this->resolveRepeater();
        
        // return $repeater->buildConfigurationFormFields($rowData, $rowToken);

        return '<p>Configuration form rendering is not implemented yet.</p>';
    }

    /**
     * Resolves the repeater field instance based on the provided repeater ID.
     *
     * @return Repeater
     *
     * @throws \Exception If the field with the given ID is not a valid repeater field.
     */
    // private function resolveRepeater(): Repeater {
        

    //     return $field;
    // }
}