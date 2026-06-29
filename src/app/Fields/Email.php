<?php 

namespace MM\Meros\App\Fields;

class Email extends Input {
    public static string $icon = 'email';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'email';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('email');
        $this->addSupport('icon');
        
        if ($this->showsIcon === null) {
            $this->showIcon(true, $this->iconPosition === null ? 'left' : $this->iconPosition);
        }
    }
}