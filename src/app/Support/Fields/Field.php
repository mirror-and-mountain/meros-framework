<?php 

namespace MM\Meros\App\Support\Fields;

use MM\Meros\App\Contracts\FieldRenderer;
use MM\Meros\App\Support\Fields\Concerns\HasFieldProps;

abstract class Field implements FieldRenderer {
    use HasFieldProps;

    /**
     * Renders the field using its designated view component.
     *
     * @return void
     */
    public function render(): void {
        $view = 'meros::components.fields.wrappers.site-field';

        echo view($view, [
            'component' => $this->getFieldComponent(),
            'field'     => $this
        ]);
    }
    

    /**
     * Retrieves the name of the Blade component responsible for rendering this field type.
     *
     * @return string
     */
    abstract public function getFieldComponent(): string;
}