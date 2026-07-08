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

    /**
     * Whether the field should load its options dynamically.
     *
     * @var bool
     */
    public bool $useDynamicOptions = false;

    /**
     * Dynamic options source identifier.
     *
     * @var string|null
     */
    protected ?string $dynamicOptionsSource = null;

    /**
     * The post type to query when using the posts dynamic options source.
     *
     * @var string|null
     */
    protected ?string $dynamicOptionsPostType = null;

    /**
     * The post status to query when using the posts dynamic options source.
     *
     * @var string|null
     */
    protected ?string $dynamicOptionsPostStatus = null;

    /**
     * The taxonomy slug to filter queried posts by.
     *
     * @var string|null
     */
    protected ?string $dynamicOptionsTaxonomy = null;

    /**
     * Comma-separated taxonomy terms used to filter queried posts.
     *
     * @var string|null
     */
    protected ?string $dynamicOptionsTerms = null;

    /**
     * The user role to filter queried users by.
     *
     * @var string|null
     */
    protected ?string $dynamicOptionsUserRole = null;

    /**
     * Maximum number of dynamic option results to request.
     *
     * @var int|null
     */
    protected ?int $dynamicOptionsLimit = null;

    /**
     * Generic source-specific dynamic option parameters.
     *
     * @var array<string, mixed>
     */
    protected array $dynamicOptionsConfig = [];

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
        $baseOptions = isset($props['options']) && is_array($props['options'])
            ? $props['options']
            : $this->options;

        $parsedProps['options'] = $this->buildRenderableOptions($baseOptions, $parsedProps['value']);
        
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

        if ($this->supports('dynamicOptions') && $this->useDynamicOptions && $this->dynamicOptionsSource !== null) {
            $dynamicConfig = $this->resolveDynamicOptionsConfig();

            $parsedProps['attributes']['data-dynamic-options-enabled'] = 'true';
            $parsedProps['attributes']['data-dynamic-options-source'] = $this->dynamicOptionsSource;
            $parsedProps['attributes']['data-dynamic-options-endpoint'] = rest_url('meros/v1/dynamic-choice-options');
            $parsedProps['attributes']['data-dynamic-options-config'] = json_encode($dynamicConfig);

            if ($this->dynamicOptionsPostType !== null) {
                $parsedProps['attributes']['data-dynamic-options-post-type'] = $this->dynamicOptionsPostType;
            }

            if ($this->dynamicOptionsPostStatus !== null) {
                $parsedProps['attributes']['data-dynamic-options-post-status'] = $this->dynamicOptionsPostStatus;
            }

            if ($this->dynamicOptionsTaxonomy !== null) {
                $parsedProps['attributes']['data-dynamic-options-taxonomy'] = $this->dynamicOptionsTaxonomy;
            }

            if ($this->dynamicOptionsTerms !== null) {
                $parsedProps['attributes']['data-dynamic-options-terms'] = $this->dynamicOptionsTerms;
            }

            if ($this->dynamicOptionsUserRole !== null) {
                $parsedProps['attributes']['data-dynamic-options-user-role'] = $this->dynamicOptionsUserRole;
            }

            if ($this->dynamicOptionsLimit !== null) {
                $parsedProps['attributes']['data-dynamic-options-limit'] = (string) $this->dynamicOptionsLimit;
            }
        }

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
     * Enables or disables dynamic options if the field supports them.
     *
     * @param bool $useDynamicOptions
     * @return self
     */
    public function useDynamicOptions(bool $useDynamicOptions = true): self {
        if ($this->supports('dynamicOptions')) {
            $this->useDynamicOptions = $useDynamicOptions;

            if ($useDynamicOptions && $this->supports('dynamicDefault')) {
                $this->useDynamicDefault = false;
                $this->dynamicDefaultType = null;
            }

            if ($useDynamicOptions) {
                $this->dynamicOptionsSource ??= 'posts';
                $this->dynamicOptionsPostType ??= 'post';
                $this->dynamicOptionsPostStatus ??= 'publish';
                $this->dynamicOptionsLimit ??= 20;

                if (!array_key_exists('postType', $this->dynamicOptionsConfig)) {
                    $this->dynamicOptionsConfig['postType'] = $this->dynamicOptionsPostType;
                }

                if (!array_key_exists('postStatus', $this->dynamicOptionsConfig)) {
                    $this->dynamicOptionsConfig['postStatus'] = $this->dynamicOptionsPostStatus;
                }

                if (!array_key_exists('limit', $this->dynamicOptionsConfig)) {
                    $this->dynamicOptionsConfig['limit'] = $this->dynamicOptionsLimit;
                }
            }
        }

        return $this;
    }

    public function useDynamicDefault(bool $useDynamicDefault = true): self {
        parent::useDynamicDefault($useDynamicDefault);

        if ($useDynamicDefault && $this->supports('dynamicOptions')) {
            $this->useDynamicOptions = false;
        }

        return $this;
    }

    public function dynamicDefault(string $dynamicDefault): self {
        parent::dynamicDefault($dynamicDefault);

        if ($this->supports('dynamicOptions')) {
            $this->useDynamicOptions = false;
        }

        return $this;
    }

    /**
     * Sets the dynamic options source.
     *
     * @param string $source
     * @return self
     */
    public function dynamicOptionsSource(string $source): self {
        if ($this->supports('dynamicOptions')) {
            $this->dynamicOptionsSource = trim($source) !== '' ? trim($source) : null;
            $this->useDynamicOptions = $this->dynamicOptionsSource !== null;

            if ($this->useDynamicOptions && $this->supports('dynamicDefault')) {
                $this->useDynamicDefault = false;
                $this->dynamicDefaultType = null;
            }

            if ($this->dynamicOptionsSource === 'posts') {
                $this->dynamicOptionsConfig['postType'] ??= $this->dynamicOptionsPostType ?? 'post';
                $this->dynamicOptionsConfig['postStatus'] ??= $this->dynamicOptionsPostStatus ?? 'publish';
                $this->dynamicOptionsConfig['limit'] ??= $this->dynamicOptionsLimit ?? 20;
            }

            if ($this->dynamicOptionsSource === 'users') {
                $this->dynamicOptionsConfig['userRole'] ??= $this->dynamicOptionsUserRole ?? '';
                $this->dynamicOptionsConfig['limit'] ??= $this->dynamicOptionsLimit ?? 20;
            }
        }

        return $this;
    }

    /**
     * Sets the queried post type for the posts dynamic options source.
     *
     * @param string $postType
     * @return self
     */
    public function dynamicOptionsPostType(string $postType): self {
        if ($this->supports('dynamicOptions')) {
            $this->dynamicOptionsPostType = trim($postType) !== '' ? trim($postType) : null;

            if ($this->dynamicOptionsPostType !== null) {
                $this->dynamicOptionsConfig['postType'] = $this->dynamicOptionsPostType;
            }
        }

        return $this;
    }

    /**
     * Sets the queried post status for the posts dynamic options source.
     *
     * @param string $postStatus
     * @return self
     */
    public function dynamicOptionsPostStatus(string $postStatus): self {
        if ($this->supports('dynamicOptions')) {
            $this->dynamicOptionsPostStatus = trim($postStatus) !== '' ? trim($postStatus) : null;

            if ($this->dynamicOptionsPostStatus !== null) {
                $this->dynamicOptionsConfig['postStatus'] = $this->dynamicOptionsPostStatus;
            }
        }

        return $this;
    }

    /**
     * Sets the taxonomy slug used to filter queried posts.
     *
     * @param string $taxonomy
     * @return self
     */
    public function dynamicOptionsTaxonomy(string $taxonomy): self {
        if ($this->supports('dynamicOptions')) {
            $this->dynamicOptionsTaxonomy = trim($taxonomy) !== '' ? trim($taxonomy) : null;

            if ($this->dynamicOptionsTaxonomy !== null) {
                $this->dynamicOptionsConfig['taxonomy'] = $this->dynamicOptionsTaxonomy;
            }
        }

        return $this;
    }

    /**
     * Sets the comma-separated terms used to filter queried posts.
     *
     * @param string $terms
     * @return self
     */
    public function dynamicOptionsTerms(string $terms): self {
        if ($this->supports('dynamicOptions')) {
            $this->dynamicOptionsTerms = trim($terms) !== '' ? trim($terms) : null;

            if ($this->dynamicOptionsTerms !== null) {
                $this->dynamicOptionsConfig['terms'] = $this->dynamicOptionsTerms;
            }
        }

        return $this;
    }

    /**
     * Sets the user role used to filter queried users.
     *
     * @param string $role
     * @return self
     */
    public function dynamicOptionsUserRole(string $role): self {
        if ($this->supports('dynamicOptions')) {
            $this->dynamicOptionsUserRole = trim($role) !== '' ? trim($role) : null;

            if ($this->dynamicOptionsUserRole !== null) {
                $this->dynamicOptionsConfig['userRole'] = $this->dynamicOptionsUserRole;
            }
        }

        return $this;
    }

    /**
     * Sets the maximum number of dynamic option results.
     *
     * @param int $limit
     * @return self
     */
    public function dynamicOptionsLimit(int $limit): self {
        if ($this->supports('dynamicOptions')) {
            $this->dynamicOptionsLimit = $limit > 0 ? $limit : null;

            if ($this->dynamicOptionsLimit !== null) {
                $this->dynamicOptionsConfig['limit'] = $this->dynamicOptionsLimit;
            }
        }

        return $this;
    }

    /**
     * Sets generic source configuration values for dynamic options.
     *
     * @param array<string, mixed>|string $config
     * @return self
     */
    public function dynamicOptionsConfig(array|string $config): self {
        if (!$this->supports('dynamicOptions')) {
            return $this;
        }

        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = is_array($decoded) ? $decoded : [];
        }

        $clean = [];

        foreach ($config as $key => $value) {
            $configKey = trim((string) $key);

            if ($configKey === '') {
                continue;
            }

            $clean[$configKey] = $value;
        }

        $this->dynamicOptionsConfig = $clean;

        if (array_key_exists('postType', $clean)) {
            $this->dynamicOptionsPostType = trim((string) $clean['postType']) !== '' ? trim((string) $clean['postType']) : null;
        }

        if (array_key_exists('postStatus', $clean)) {
            $this->dynamicOptionsPostStatus = trim((string) $clean['postStatus']) !== '' ? trim((string) $clean['postStatus']) : null;
        }

        if (array_key_exists('taxonomy', $clean)) {
            $this->dynamicOptionsTaxonomy = trim((string) $clean['taxonomy']) !== '' ? trim((string) $clean['taxonomy']) : null;
        }

        if (array_key_exists('terms', $clean)) {
            $this->dynamicOptionsTerms = trim((string) $clean['terms']) !== '' ? trim((string) $clean['terms']) : null;
        }

        if (array_key_exists('userRole', $clean)) {
            $this->dynamicOptionsUserRole = trim((string) $clean['userRole']) !== '' ? trim((string) $clean['userRole']) : null;
        }

        if (array_key_exists('limit', $clean)) {
            $parsedLimit = (int) $clean['limit'];
            $this->dynamicOptionsLimit = $parsedLimit > 0 ? $parsedLimit : null;
        }

        return $this;
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

        if (!$isDynamic && $this->supports('dynamicOptions') && $this->useDynamicOptions) {
            if (is_array($default) && ($this->attributes['multiple'] ?? false)) {
                $this->default = array_values(array_filter(
                    array_map(fn ($value) => trim((string) $value), $default),
                    fn ($value) => $value !== ''
                ));

                return $this;
            }

            if (is_scalar($default)) {
                $this->default = (string) $default;

                return $this;
            }
        }

        if ($isDynamic && $this->supports('dynamicDefault')) {
            $this->default = $default;
        }

        else if (!$isDynamic) {
            if (is_array($default) && ($this->attributes['multiple'] ?? false)) {
                $this->default = [];

                foreach ($default as $value) {
                    $value = (string) $value;
                    $value = Str::snake($value);    

                    if (!array_key_exists($value, $this->options)) {
                        $this->options[$value] = Str::title(str_replace(['-', '_'], ' ', $value));
                    }

                    $this->default[] = $value;
                }

                return $this;
            }

            else {
                if (is_scalar($default)) {
                    $defaultKey = is_string($default)
                        ? Str::snake($default)
                        : (string) $default;

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


    // =========================================================================
    // Serialisation
    // =========================================================================

    /**
     * Converts the field's properties to an array format suitable for JSON serialization
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = parent::toJson(false);

        $json['properties']['options'] = $this->options;
        $json['properties']['useDynamicOptions'] = $this->useDynamicOptions;
        $json['properties']['dynamicOptionsSource'] = $this->dynamicOptionsSource;
        $json['properties']['dynamicOptionsPostType'] = $this->dynamicOptionsPostType;
        $json['properties']['dynamicOptionsPostStatus'] = $this->dynamicOptionsPostStatus;
        $json['properties']['dynamicOptionsTaxonomy'] = $this->dynamicOptionsTaxonomy;
        $json['properties']['dynamicOptionsTerms'] = $this->dynamicOptionsTerms;
        $json['properties']['dynamicOptionsUserRole'] = $this->dynamicOptionsUserRole;
        $json['properties']['dynamicOptionsLimit'] = $this->dynamicOptionsLimit;
        $json['properties']['dynamicOptionsConfig'] = $this->resolveDynamicOptionsConfig();

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }

    /**
     * Retrieves the options for the field.
     *
     * @return array
     */
    public function getOptions(): array {
        return $this->options;
    }

    /**
     * Retrieves the dynamic options source, if configured.
     *
     * @return string|null
     */
    public function getDynamicOptionsSource(): ?string {
        return $this->dynamicOptionsSource;
    }

    /**
     * Returns whether dynamic options are enabled for the field.
     *
     * @return bool
     */
    public function usesDynamicOptions(): bool {
        return $this->useDynamicOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDynamicOptionsConfig(): array {
        return $this->resolveDynamicOptionsConfig();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDynamicOptionsConfig(): array {
        $config = $this->dynamicOptionsConfig;

        if ($this->dynamicOptionsPostType !== null && !array_key_exists('postType', $config)) {
            $config['postType'] = $this->dynamicOptionsPostType;
        }

        if ($this->dynamicOptionsPostStatus !== null && !array_key_exists('postStatus', $config)) {
            $config['postStatus'] = $this->dynamicOptionsPostStatus;
        }

        if ($this->dynamicOptionsTaxonomy !== null && !array_key_exists('taxonomy', $config)) {
            $config['taxonomy'] = $this->dynamicOptionsTaxonomy;
        }

        if ($this->dynamicOptionsTerms !== null && !array_key_exists('terms', $config)) {
            $config['terms'] = $this->dynamicOptionsTerms;
        }

        if ($this->dynamicOptionsUserRole !== null && !array_key_exists('userRole', $config)) {
            $config['userRole'] = $this->dynamicOptionsUserRole;
        }

        if ($this->dynamicOptionsLimit !== null && !array_key_exists('limit', $config)) {
            $config['limit'] = $this->dynamicOptionsLimit;
        }

        if (!array_key_exists('limit', $config)) {
            $config['limit'] = 20;
        }

        return $config;
    }

    private function buildRenderableOptions(array $options, mixed $value): array {
        $resolvedValues = is_array($value) ? $value : [$value];

        foreach ($resolvedValues as $resolvedValue) {
            if (!is_scalar($resolvedValue)) {
                continue;
            }

            $optionValue = (string) $resolvedValue;

            if ($optionValue === '' || array_key_exists($optionValue, $options)) {
                continue;
            }

            $options[$optionValue] = $this->buildDynamicOptionLabel($optionValue);
        }

        return $options;
    }

    private function buildDynamicOptionLabel(string $value): string {
        return Str::title(str_replace(['-', '_'], ' ', $value));
    }
}