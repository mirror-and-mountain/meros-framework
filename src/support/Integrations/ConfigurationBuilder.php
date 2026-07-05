<?php

namespace MM\Meros\Support\Integrations;

final class ConfigurationBuilder {
    /**
     * @var array<int, ConfigurationField>
     */
    protected array $fields = [];

    public function text(string $name): ConfigurationField {
        return $this->addField($name, 'text', 'string');
    }

    public function password(string $name): ConfigurationField {
        return $this->addField($name, 'password', 'string');
    }

    public function textarea(string $name): ConfigurationField {
        return $this->addField($name, 'textarea', 'string');
    }

    public function boolean(string $name): ConfigurationField {
        return $this->addField($name, 'checkbox', 'boolean');
    }

    public function number(string $name): ConfigurationField {
        return $this->addField($name, 'number', 'number');
    }

    public function select(string $name): ConfigurationField {
        return $this->addField($name, 'select', 'string');
    }

    public function multiSelect(string $name): ConfigurationField {
        return $this->addField($name, 'multi_select', 'array');
    }

    /**
     * @return array<int, ConfigurationField>
     */
    public function all(): array {
        return $this->fields;
    }

    protected function addField(string $name, string $fieldType, string $dataType): ConfigurationField {
        $field = new ConfigurationField($name, $fieldType, $dataType);
        $this->fields[] = $field;

        return $field;
    }
}