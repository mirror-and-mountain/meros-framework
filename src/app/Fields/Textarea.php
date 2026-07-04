<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Forms\Field;

class Textarea extends Field {
    public static string $icon = 'bars-long';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        $this->handle = 'textarea';
        $this->compatibleDataTypes = ['string'];

        $this->attribute('rows', 3);

        $this->addSupports([
            'required',
            'disabled',
            'helpText',
            'placeholder',
            'dynamicDefault'
        ]);
    }

    /**
     * Sets the number of rows for the textarea input.
     *
     * @param integer $rows
     *
     * @return self
     */
    public function rows(int $rows): self {
        $this->attribute('rows', $rows);
        return $this;
    }
    
    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.textarea';
    }
}