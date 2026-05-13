<?php 

namespace MM\Meros\App\Fields;

class Number extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'number';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'calculator';

    /**
     * Default attributes for the number field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'number',
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'integer',
        'float',
        'decimal',
    ];


    /***************************
     * Fluent Setters
     ***************************/
    /**
     * Sets the minimum attribute for number inputs.
     *
     * @param float $min The minimum value allowed for the input.
     *
     * @return self
     */
    public function min(float $min): self {
        $this->attribute('min', $min);
        return $this;
    }
    
    /**
     * Sets the maximum attribute for number inputs.
     *
     * @param float $max The maximum value allowed for the input.
     *
     * @return self
     */
    public function max(float $max): self {
        $this->attribute('max', $max);
        return $this;
    }

    /**
     * Sets the step attribute for number inputs.
     *
     * @param float $step The step value for the input.
     *
     * @return self
     */
    public function step(float $step): self {
        $this->attribute('step', $step);
        return $this;
    }
}