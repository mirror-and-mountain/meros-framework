<?php 

namespace MM\Meros\App\Fields;

class Color extends Input {
    public static string $icon = 'swatch';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'color';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('color');
    }
}