<?php 

namespace MM\Meros\App\Fields;

class Text extends Input {
    public static string $icon = 'bars';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'text';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('text');
    }
}