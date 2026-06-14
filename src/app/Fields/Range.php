<?php 

namespace MM\Meros\App\Fields;

class Range extends Number {
    public static string $icon = 'adjustments';
    
    /**
     * Whether the range field should show a number input alongside the range slider.
     *
     * @var boolean
     */
    protected bool $showsNumberInput = true;

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        $this->handle = 'range';
        $this->compatibleDataTypes = ['integer', 'float', 'decimal'];

        $this->inputType('range');
    }

    /**
     * Sets the range field to show a number input alongside the range slider.
     *
     * @param boolean $showsNumberInput
     *
     * @return self
     */
    public function withNumberInput(bool $showsNumberInput = true): self {
        $this->showsNumberInput = $showsNumberInput;
        return $this;
    }

    /**
     * Checks if the range field is set to show a number input alongside the range slider.
     *
     * @return boolean
     */
    public function showsNumberInput(): bool {
        return $this->showsNumberInput;
    }

    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.range';
    }
}