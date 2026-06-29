<?php 

namespace MM\Meros\App\Fields;

class Date extends Input {
    public static string $category = 'dates';
    public static string $icon = 'calendar';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'date';
        $this->compatibleDataTypes = ['string'];

        $this->inputType('date');
        $this->addSupport('icon');
        
        if ($this->showsIcon === null) {
            $this->showIcon(true, $this->iconPosition ?? 'left');
        }
    }
}