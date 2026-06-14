<?php 

namespace MM\Meros\App\Fields;

class Hidden extends Input {
    public static string $icon = 'eye-slash';
    public static string $category = 'specialised';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'hidden';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('hidden');
    }

}