<?php 

namespace MM\Meros\App\Fields;

class AdvancedSelect extends Select {
    protected bool   $useDynamicOptions = false;
    protected string $dynamicOptionsSource = '';
    protected array  $dynamicOptionsConfig = [];

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'advanced_select';
        $this->compatibleDataTypes = ['string'];

        $this->addSupport('dynamicDefault');
        $this->addSupport('dynamicOptions');

        $this->attribute('data-allow-add', 'false');
        $this->attribute('data-advanced', 'true');
    }

    /**
     * Sets whether the field allows adding new options on the fly.
     *
     * @param bool $allow
     *
     * @return self
     */
    public function allowAdd(bool $allow = true): self {
        $this->attributes['data-allow-add'] = $allow ? 'true' : 'false';
        return $this;
    }

    // =========================================================================
    // Rendering
    // =========================================================================
    
    /**
     * Renders the field
     *
     * @param boolean $wrapper
     * @param array   $props
     *
     * @return void
     */
    public function render(bool $wrapper = true, array $props = []): void {
        if ($this->useDynamicOptions) {
            $this->attribute('data-uses-dynamic-options', 'true');
            
            $config = $this->dynamicOptionsConfig;
            $config['source'] = $this->dynamicOptionsSource;
            $this->attribute('data-dynamic-options-config', json_encode($config));

            $this->attribute('data-dynamic-options-endpoint', rest_url('meros/v1/dynamic-options'));
            $this->attribute('data-dynamic-options-default-value', is_array($this->default) ? json_encode($this->default) : $this->default);
        }

        parent::render($wrapper, $props);
    }


    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.select-advanced';
    }

    // =========================================================================
    // Dynamic Options
    // =========================================================================

    /**
     * Sets whether the field uses dynamic options from a registered source.
     *
     * @param bool|array $useDynamicOptions
     * @param array      $config
     *
     * @return self
     */
    public function useDynamicOptions(bool|array $useDynamicOptions = true, array $config = []): self {
        $config = is_array($useDynamicOptions) ? $useDynamicOptions : $config;
        $this->useDynamicOptions = is_array($useDynamicOptions) ? true : $useDynamicOptions;
        $this->dynamicOptionsConfig = $config;

        if ($this->useDynamicOptions && !empty($config['source'])) {
            $this->dynamicOptionsSource = (string) $config['source'];
        }

        $this->options = [];

        return $this;
    }

    /**
     * Sets the source for dynamic options for this field.
     *
     * @param string $source
     *
     * @return self
     */
    public function source(string $source): self {
        $this->dynamicOptionsSource = $source;

        return $this;
    }

    /**
     * Sets a configuration value for dynamic options for this field.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return self
     */
    public function dynamicOptionsConfig(string $key, mixed $value): self {
        $this->dynamicOptionsConfig[$key] = $value;

        return $this;
    }

    /**
     * Retrieves a configuration value for dynamic options for this field.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getDynamicOptionsConfig(string $key, mixed $default = null): mixed {
        return $this->dynamicOptionsConfig[$key] ?? $default;
    }

    /**
     * Retrieves the source for dynamic options for this field.
     *
     * @return string
     */
    public function getDynamicOptionsSource(): string {
        return $this->dynamicOptionsSource;
    }

    /**
     * Retrieves the configuration for dynamic options for this field.
     *
     * @return bool
     */
    public function usesDynamicOptions(): bool {
        return $this->useDynamicOptions;
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

        $json['properties']['useDynamicOptions'] = $this->useDynamicOptions;
        $json['properties']['dynamicOptionsSource'] = $this->dynamicOptionsSource;
        $json['properties']['dynamicOptionsConfig'] = $this->dynamicOptionsConfig;

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }
}