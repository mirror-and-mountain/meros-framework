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
     * The category for the field, used for grouping in the UI.
     *
     * @var string
     */
    public static string $category = 'dates';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'calendar';

    /**
     * Default attributes for the date field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'date',
    ];

    /**
     * Rules supported by the field for validation purposes.
     *
     * @var array
     */
    protected array $supportedRules = [
        'min',
        'max',
        'step'
    ];

    /**
     * Supported features for the date field.
     *
     * @var array
     */
    protected array $supports = [
        'placeholder',
        'icon'
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