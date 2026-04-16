<?php 

namespace MM\Meros\App\Support\Fields;

class Input extends Field {
    public string $variation = '';
    public string $placeholder = '';
    public ?float $min = null;
    public ?float $max = null;
    public ?float $step = null;


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
    public function variation(string $variation): self {
        if (!in_array($variation, $this->validTypes)) {
            throw new \InvalidArgumentException("Invalid field variation '{$variation}'. Valid variations are: " . implode(', ', $this->validTypes));
        }

        $this->variation = $variation;

        return $this;
    }

    public function configure(array $config): self {
        switch ($this->variation) {
            case 'number':

                if (isset($config['min'])) {
                    $this->min = $config['min'];
                }
                if (isset($config['max'])) {
                    $this->max = $config['max'];
                }
                if (isset($config['step'])) {
                    $this->step = $config['step'];
                }
                break;

            case 'text':
            case 'email':
            case 'url':
            case 'tel':
            case 'password':

                if (isset($config['placeholder'])) {
                    $this->placeholder = $config['placeholder'];
                }
                break;
        }

        return parent::configure($config);
    }

    public function placeholder(string $text): self {
        $this->placeholder = $text;
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
        return $this->variation === 'checkbox' 
            ? 'meros::fields.checkbox' 
            : 'meros::fields.input';
    }
}