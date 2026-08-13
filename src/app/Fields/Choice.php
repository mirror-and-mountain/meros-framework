<?php

namespace MM\Meros\App\Fields;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\Forms\Field;

abstract class Choice extends Field {
    public static string $category = 'choice';
    public static string $icon = 'list';

    /**
     * An array of options for fields that support choices, such as select or radio fields.
     *
     * @var array
     */
    protected array $options = [];

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        $this->handle = 'choice';
        $this->addSupports([
            'required',
            'disabled',
            'placeholder',
            'helpText',
            'options'
        ]);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Retrieves the rendering properties for the field, applying defaults where necessary.
     *
     * @param array $props An array of properties that may include 'id', 'label', 'helpText', 'excludeAttributes', and 'component'.
     *
     * @return array An array containing the parsed properties with defaults applied.
     */
    protected function getRenderProps(array $props = []): array {
        $parsedProps = parent::getRenderProps($props);
        $parsedProps['options'] = isset($props['options']) && is_array($props['options'])
            ? $props['options']
            : $this->options;
        
        return $parsedProps;
    }

    /**
     * Parses the rendering properties for the field, applying defaults where necessary.
     *
     * @param array $props An array of properties that may include 'id', 'label', 'helpText', 'excludeAttributes', and 'component'.
     *
     * @return array An array containing the parsed properties with defaults applied.
     */
    protected function parseRenderProps(array $props): array {
        $parsedProps = parent::parseRenderProps($props);
        $parsedProps['options'] = isset($props['options']) && is_array($props['options']) ? $props['options'] : $this->options;


        return $parsedProps;
    }


    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the options for fields that support choices, such as select or radio fields.
     *
     * @param array $options An associative array of options in the format ['value' => 'Label'].
     *
     * @return self
     */
    public function options(array $options): self {
        if ($this->options === null) {
            $this->options = [];
        }

        foreach ($options as $value => $label) {
            if (is_int($value)) {
                $value = Str::snake($label);
            }
            $this->options[$value] = $label;
        }

        return $this;
    }

    /**
     * Replaces the current option set with the provided options.
     *
     * @param array $options
     *
     * @return self
     */
    public function setOptions(array $options): self {
        $this->options = [];
        return $this->options($options);
    }

    /**
     * Sets a default value for the field.
     *
     * @param mixed $default
     *
     * @return self
     */
    public function default(mixed $default): self {
        $isDynamic = is_string($default) && 
            Str::startsWith($default, '{{') && 
            Str::endsWith($default, '}}');

        if ($isDynamic && $this->supports('dynamicDefault')) {
            $this->default = $default;
        }

        else if (!$isDynamic) {
            if (is_array($default) && ($this->attributes['multiple'] ?? false)) {
                $this->default = [];

                foreach ($default as $value) {
                    $value = (string) $value;

                    if (!array_key_exists($value, $this->options)) {
                        $this->options[$value] = Str::title(str_replace(['-', '_'], ' ', $value));
                    }

                    $this->default[] = $value;
                }

                return $this;
            }

            else {
                if (is_scalar($default)) {
                    $defaultKey = (string) $default;

                    if (!array_key_exists($defaultKey, $this->options)) {
                        $this->options[$defaultKey] = Str::title(str_replace(['-', '_'], ' ', $defaultKey));
                    }

                    $default = $defaultKey;
                }
            }
        }

        $this->default = $default;
        return $this;
    }

    /**
     * Retrieves the options for the field.
     *
     * @return array
     */
    public function getOptions(): array {
        return $this->options;
    }

    // =========================================================================
    // Serialisation
    // =========================================================================

    /**
     * Converts the field's properties to an array format suitable for JSON serialization
     * 
     * @param bool    $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = parent::toJson(false);

        $json['properties']['options'] = $this->options;

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }
}