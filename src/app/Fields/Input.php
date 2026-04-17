<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Field;

class Input extends Field {
    /**
     * The type of the input field (e.g., 'text', 'email', 'number').
     *
     * @var string
     */
    protected string $type = 'text';

    /**
     * The placeholder text for the input field.
     *
     * @var string
     */
    protected string $placeholder = '';

    /**
     * The minimum attribute for number inputs.
     *
     * @var float|null
     */
    protected ?float $min = null;

    /**
     * The maximum attribute for number inputs.
     *
     * @var float|null
     */
    protected ?float $max = null;

    /**
     * The step attribute for number inputs.
     *
     * @var float|null
     */
    protected ?float $step = null;

    /**
     * Valid variations for the input field.
     *
     * @var array
     */
    protected array $validTypes = [
        'text',
        'email',
        'number',
        'password',
        'url',
        'tel',
        'checkbox'
    ];

    /***************************
     * Public Chainable methods
     ***************************/
    /**
     * Sets the field variation and applies any additional configuration.
     *
     * @param  string $type The variation of the input field (e.g., 'text', 'email', 'number').
     * 
     * @return self
     */
    public function type(string $type): self {
        if (!in_array($type, $this->validTypes)) {
            throw new \InvalidArgumentException("Invalid field type '{$type}'. Valid types are: " . implode(', ', $this->validTypes));
        }

        $this->type = $type;

        return $this;
    }

    /**
     * Sets the placeholder text for the input field.
     *
     * @param string $text The placeholder text to display when the input is empty.
     *
     * @return self
     */
    public function placeholder(string $text): self {
        $this->placeholder = $text;
        return $this;
    }

    /**
     * Sets the minimum attribute for number inputs.
     *
     * @param float $min The minimum value allowed for the input.
     *
     * @return self
     */
    public function min(float $min): self {
        $this->min = $min;
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
        $this->max = $max;
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
        $this->step = $step;
        return $this;
    }

    /***************************
     * Rendering
     ***************************/
    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return $this->type === 'checkbox' 
            ? 'fields.checkbox' 
            : 'fields.input';
    }
}