<?php 

namespace MM\Meros\App\Fields;

class Url extends Input {
    public static string $icon = 'link';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'url';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('url');
        $this->addSupport('icon');
    }
}