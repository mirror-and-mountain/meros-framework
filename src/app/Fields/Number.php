<?php 

namespace MM\Meros\App\Fields;

class Number extends Input {
    public static string $icon = 'calculator';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'number';
        $this->compatibleDataTypes = ['integer', 'float', 'decimal'];

        $this->inputType('number');
    }

    /**
     * Sets the minimum value for the number input field, if supported.
     *
     * @param float $min
     *
     * @return self
     */
    public function min(float $min): self {
        $this->attribute('min', $min);

        return $this;
    }

    /**
     * Sets the maximum value for the number input field, if supported.
     *
     * @param float $max
     *
     * @return self
     */
    public function max(float $max): self {
        $this->attribute('max', $max);

        return $this;
    }

    /**
     * Sets the step value for the number input field, if supported.
     *
     * @param float $step
     *
     * @return self
     */
    public function step(float $step): self {
        $this->attribute('step', $step);

        return $this;
    }
}