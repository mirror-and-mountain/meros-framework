<?php 

namespace MM\Meros\App\Support\Fields;

use MM\Meros\App\Support\Settings\Setting;

class RepeaterTable extends Field {
    /**
     * Renders the repeater table field.
     *
     * @return void
     */
    public function render(): void {
        $view = 'meros::components.fields.wrappers.repeater';

        if ($this->registrar instanceof Setting) {
            $view = 'meros::components.fields.wrappers.setting-repeater';
        }

        echo view($view, [
            'rows'  => $this->buildRows(),
            'field' => $this
        ]);
    }

    /**
     * Builds RepeaterRow instances for each row of data in the repeater field.
     *
     * @return RepeaterRow[]
     */
    protected function buildRows(): array {
        $items = is_array($this->value) && !empty($this->value)
            ? $this->value
            : [[]];

        $rows = [];

        foreach ($items as $index => $rowData) {
            $rows[] = new RepeaterRow(
                repeater: $this,
                index: $index,
                rowData: $rowData
            );
        }

        return $rows;
    }

    /**
     * Retrieves the names of all sub-items defined for the repeater field.
     *
     * @return array
     */
    public function getFieldNames(): array {
        return method_exists($this->registrar, 'getItemNames')
            ? $this->registrar->getItemNames()
            : [];
    }

    /**
     * Retrieves the labels of all sub-items defined for the repeater field.
     *
     * @return array
     */
    public function getFieldLabels(): array {
        return method_exists($this->registrar, 'getItemLabels')
            ? $this->registrar->getItemLabels()
            : [];
    }

    /**
     * Returns the name of the Blade component used to render the repeater field.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::fields.repeater';
    }
}