<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Elements\Field;

class Input extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'input';

    /**
     * Supported attributes for the input field.
     *
     * @var array
     */
    protected array $supports = [
        'placeholder'
    ];

    /**
     * Default attributes for the input field.
     *
     * @var array
     */
    protected array $attributes = [
        'type' => 'text',
    ];

    /**
     * Checks if the field supports a given feature.
     *
     * @param string $feature The feature to check (e.g., 'multiple', 'advanced').
     *
     * @return bool True if the feature is supported, false otherwise.
     */
    protected function supports(string $feature): bool {
        return in_array($feature, $this->supports);
    }

    /***************************
     * Fluent Setters
     ***************************/

    /**
     * Sets the placeholder text for the input field.
     *
     * @param string $placeholder
     *
     * @return self
     */
    public function placeholder(string $placeholder): self {
        if ($this->supports('placeholder')) {
            $this->attribute('placeholder', $placeholder);
        }
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
        return 'meros::fields.input';
    }

    /***************************
     * Getters
     ***************************/
    /**
     * Gets the placeholder text for the input field, if set.
     *
     * @return string
     */
    public function getPlaceholder(): string {
        return $this->attributes['placeholder'] ?? '';
    }

    /**
     * Converts the field's properties to an array format suitable for JSON serialization
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = parent::toJson();
        
        if ($this->supports('placeholder')) {
            $json['properties']['placeholder'] = $this->getPlaceholder();
        }
        
        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }
}