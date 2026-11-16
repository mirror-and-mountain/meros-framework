<?php 

namespace MM\Meros\App\Components\Fields;

use MM\Meros\Contracts\Features\Components\FieldGroup;
use MM\Meros\Contracts\Features\Components\Fields\Input;

class Checkbox extends Input {
    /**
     * If enabled, renders the checkbox as a toggle switch.
     *
     * @var boolean
     */
    protected bool $toggle = false;

    /**
     * If enabled, renders the checkbox horizontally.
     *
     * @var boolean
     */
    protected bool $horizontal = true;

    protected function configure(): void {
        $this->type('checkbox');
        $this->dataType('boolean');
        $this->additionalDataTypes(['string', 'integer']);
        $this->inputType('checkbox');
        $this->wrapper('site', '');
        $this->view('meros::forms.fields.checkbox');
        $this->setSerializableProperties(['toggle', 'horizontal']);
    }

    /**
     * Sets the checkbox to render as a toggle switch.
     *
     * @param boolean $toggle
     *
     * @return self
     */
    final public function toggle(bool $toggle = true): self {
        $this->toggle = $toggle;
        return $this;
    }

    /**
     * Sets the checkbox to render vertically.
     *
     * @param boolean $vertical
     *
     * @return self
     */
    final public function vertical(bool $vertical = true): self {
        $this->horizontal = ! $vertical;
        if ($this->horizontal === false) {
            $this->wrapper('site', 'meros::forms.field-wrappers.default');
        }
        return $this;
    }

    /**
     * Adds builder controls specific to the checkbox field.
     *
     * @param FieldGroup $group
     * @param boolean    $locked
     * @param array      $rowData The data for the current row in the form builder.
     *
     * @return FieldGroup
     */
    protected static function addBuilderControls(FieldGroup $group, bool $locked = false, array $rowData = []): FieldGroup {
        $group->field('checkbox', [
            'name'  => 'defaultValue',
            'label' => 'Default Value',
            'description' => "The field's default value."
        ]);

        return $group;
    }
}