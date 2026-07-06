<?php

namespace MM\Meros\Support\Integrations;

/**
 * Represents a single configuration field for an integration.
 */
final class ConfigurationField {
    /**
     * The name of the configuration field.
     *
     * @var string
     */
    protected string $name;

    /**
     * The type of the configuration field (e.g., text, password, select).
     *
     * @var string
     */
    protected string $fieldType;

    /**
     * The data type of the configuration field (e.g., string, boolean, array).
     *
     * @var string
     */
    protected string $dataType;

    /**
     * The human-readable label for the configuration field.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The human-readable description for the configuration field.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The help text for the configuration field.
     *
     * @var string|null
     */
    protected ?string $helpText = null;

    /**
     * The default value for the configuration field.
     *
     * @var mixed
     */
    protected mixed $default = null;

    /**
     * The options for the configuration field (used for select/multi-select fields).
     *
     * @var array
     */
    protected array $options = [];

    /**
     * Indicates whether the configuration field is encrypted (e.g., for sensitive data).
     *
     * @var bool
     */
    protected bool $encrypted = false;

    public function __construct(string $name, string $fieldType, string $dataType) {
        $this->name = $name;
        $this->fieldType = $fieldType;
        $this->dataType = $dataType;
        $this->encrypted = $fieldType === 'password';
    }

    /**
     * Sets the label for the configuration field.
     *
     * @param string $label The human-readable label.
     * @return self
     */
    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the description for the configuration field.
     *
     * @param string $description The human-readable description.
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Sets the help text for the configuration field.
     *
     * @param string $helpText The help text.
     * @return self
     */
    public function helpText(string $helpText): self {
        $this->helpText = $helpText;
        return $this;
    }

    /**
     * Sets the default value for the configuration field.
     *
     * @param mixed $default The default value.
     * @return self
     */
    public function default(mixed $default): self {
        $this->default = $default;
        return $this;
    }

    /**
     * Sets the options for the configuration field (used for select/multi-select fields).
     *
     * @param array $options The options for the field.
     * @return self
     */
    public function options(array $options): self {
        $this->options = $options;
        return $this;
    }

    /**
     * Sets whether the configuration field is encrypted (e.g., for sensitive data).
     *
     * @param bool $encrypted Whether the field should be encrypted.
     * @return self
     */
    public function encrypted(bool $encrypted = true): self {
        $this->encrypted = $encrypted;
        return $this;
    }

    /**
     * Sets whether the configuration field is sensitive (alias for encrypted).
     *
     * @param bool $sensitive Whether the field should be treated as sensitive.
     * @return self
     */
    public function sensitive(bool $sensitive = true): self {
        return $this->encrypted($sensitive);
    }

    /**
     * Applies the configuration field to a given setting object.
     *
     * @param object $setting The setting object to apply the field to.
     * @param string|null $nameOverride Optional name override for the field.
     */
    public function applyTo(object $setting, ?string $nameOverride = null): void {
        $type = $this->dataType;
        $name = $nameOverride ?? $this->name;

        if (method_exists($setting, $type)) {
            $setting->{$type}($name);
        }

        if (method_exists($setting, 'field')) {
            $setting->field($this->fieldType, function ($field) {
                if ($this->label !== '') {
                    $field->label($this->label);
                }

                if ($this->description !== '') {
                    $field->helpText($this->description);
                }

                if ($this->helpText !== null && $this->helpText !== '') {
                    $field->helpText($this->helpText);
                }

                if ($this->default !== null) {
                    $field->default($this->default);
                }

                if ($this->options !== []) {
                    $field->options($this->options);
                }
            });
        }
    }

    /**
     * Retrieves the name of the configuration field.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Retrieves the type of the configuration field.
     *
     * @return string
     */
    public function getFieldType(): string {
        return $this->fieldType;
    }

    /**
     * Retrieves the data type of the configuration field.
     *
     * @return string
     */
    public function getDataType(): string {
        return $this->dataType;
    }

    /**
     * Returns whether the configuration field is encrypted (e.g., for sensitive data).
     *
     * @return bool
     */
    public function isEncrypted(): bool {
        return $this->encrypted;
    }
}