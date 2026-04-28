<?php 

namespace MM\Meros\App\Fields;

class Date extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'date';

    /**
     * Default attributes for the date field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'date',
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'string'
    ];


    /***************************
     * Fluent Setters
     ***************************/
    /**
     * Sets the minimum attribute for date inputs.
     *
     * @param string $min The minimum date allowed for the input.
     *
     * @return self
     */
    public function min(string $min): self {
        $this->attribute('min', $min);
        return $this;
    }
    
    /**
     * Sets the maximum attribute for date inputs.
     *
     * @param string $max The maximum date allowed for the input.
     *
     * @return self
     */
    public function max(string $max): self {
        $this->attribute('max', $max);
        return $this;
    }

    /**
     * Sets the step attribute for date inputs.
     *
     * @param string $step The step value for the input.
     *
     * @return self
     */
    public function step(string $step): self {
        $this->attribute('step', $step);
        return $this;
    }
}