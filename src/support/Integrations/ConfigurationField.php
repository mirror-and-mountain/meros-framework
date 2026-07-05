<?php

namespace MM\Meros\Support\Integrations;

final class ConfigurationField {
    protected string $name;

    protected string $fieldType;

    protected string $dataType;

    protected string $label = '';

    protected string $description = '';

    protected ?string $helpText = null;

    protected mixed $default = null;

    protected array $options = [];

    public function __construct(string $name, string $fieldType, string $dataType) {
        $this->name = $name;
        $this->fieldType = $fieldType;
        $this->dataType = $dataType;
    }

    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function helpText(string $helpText): self {
        $this->helpText = $helpText;
        return $this;
    }

    public function default(mixed $default): self {
        $this->default = $default;
        return $this;
    }

    public function options(array $options): self {
        $this->options = $options;
        return $this;
    }

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

    public function getName(): string {
        return $this->name;
    }

    public function getFieldType(): string {
        return $this->fieldType;
    }

    public function getDataType(): string {
        return $this->dataType;
    }
}