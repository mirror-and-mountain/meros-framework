<?php 

namespace MM\Meros\App\Fields;

class Range extends Number {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'range';

    /**
     * Default attributes for the range field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'range',
    ];
    
    /**
     * Whether the field shows a number input alongside the range slider.
     *
     * @var boolean
     */
    public bool $showsNumberInput = true;

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