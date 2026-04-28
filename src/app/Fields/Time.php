<?php 

namespace MM\Meros\App\Fields;

class Time extends Input {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'time';

    /**
     * Default attributes for the time field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'time',
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
     * Sets the minimum attribute for time inputs.
     *
     * @param string $min The minimum time allowed for the input.
     *
     * @return self
     */
    public function min(string $min): self {
        $this->attribute('min', $min);
        return $this;
    }
    
    /**
     * Sets the maximum attribute for time inputs.
     *
     * @param string $max The maximum time allowed for the input.
     *
     * @return self
     */
    public function max(string $max): self {
        $this->attribute('max', $max);
        return $this;
    }

    /**
     * Sets the step attribute for time inputs.
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