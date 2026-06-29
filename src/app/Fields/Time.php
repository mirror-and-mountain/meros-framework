<?php 

namespace MM\Meros\App\Fields;

class Time extends Input {
    public static string $category = 'dates';
    public static string $icon = 'clock';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'time';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('time');
        $this->addSupport('icon');
        
        if ($this->showsIcon === null) {
            $this->showIcon(true, $this->iconPosition ?? 'left');
        }
    }
}