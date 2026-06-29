<?php 

namespace MM\Meros\App\Fields;

class Password extends Input {
    public string $handle = 'password';
    public static string $category = 'specialised';
    public static string $icon = 'lock';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'password';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('password');
        $this->addSupport('icon');
        
        if ($this->showsIcon === null) {
            $this->showIcon(true, $this->iconPosition ?? 'left');
        }
    }
}