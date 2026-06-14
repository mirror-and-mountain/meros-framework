<?php 

namespace MM\Meros\App\Fields;

class Radio extends Select {
    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'radio';
        $this->compatibleDataTypes = ['string'];

        $this->attribute('type', 'radio');
    }

    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.choice';
    }

    /**
     * Retrieves the default value control for the field.
     *
     * @return string
     */
    public function getDefaultValueControl(): string {
        return 'meros::forms.fields.select';
    }
}