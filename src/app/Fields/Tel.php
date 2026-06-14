<?php 

namespace MM\Meros\App\Fields;

class Tel extends Input {
    public static string $icon = 'phone';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'tel';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('tel');
        $this->addSupport('icon');
    }
}