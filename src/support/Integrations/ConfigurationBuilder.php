<?php

namespace MM\Meros\Support\Integrations;

/**
 * Class ConfigurationBuilder
 *
 * This class provides a fluent interface for building configuration fields for integrations.
 * It allows developers to define various types of configuration fields (text, password, textarea, etc.)
 * and their associated properties (label, description, default value, etc.).
 */
final class ConfigurationBuilder {
    /**
     * Fields that have been added to the configuration builder.
     * 
     * @var array<int, ConfigurationField>
     */
    protected array $fields = [];

    /**
     * Adds a text input field to the configuration.
     *
     * @param string $name The name of the field.
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    public function text(string $name): ConfigurationField {
        return $this->addField($name, 'text', 'string');
    }

    /**
     * Adds a password input field to the configuration.
     *
     * @param string $name The name of the field.
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    public function password(string $name): ConfigurationField {
        return $this->addField($name, 'password', 'string');
    }

    /**
     * Adds a textarea input field to the configuration.
     *
     * @param string $name The name of the field.
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    public function textarea(string $name): ConfigurationField {
        return $this->addField($name, 'textarea', 'string');
    }

    /**
     * Adds a boolean (checkbox) input field to the configuration.
     *
     * @param string $name The name of the field.
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    public function boolean(string $name): ConfigurationField {
        return $this->addField($name, 'checkbox', 'boolean');
    }

    /**
     * Adds a number input field to the configuration.
     *
     * @param string $name The name of the field.
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    public function number(string $name): ConfigurationField {
        return $this->addField($name, 'number', 'number');
    }

    /**
     * Adds a select input field to the configuration.
     *
     * @param string $name The name of the field.
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    public function select(string $name): ConfigurationField {
        return $this->addField($name, 'select', 'string');
    }

    /**
     * Adds a multi-select input field to the configuration.
     *
     * @param string $name The name of the field.
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    public function multiSelect(string $name): ConfigurationField {
        return $this->addField($name, 'multi_select', 'array');
    }

    /**
     * Retrieves all configuration fields.
     *
     * @return array<int, ConfigurationField> An array of all configuration fields.
     */
    public function all(): array {
        return $this->fields;
    }

    /**
     * Adds a new configuration field to the builder.
     *
     * @param string $name The name of the field.
     * @param string $fieldType The type of the field (e.g., text, password, select).
     * @param string $dataType The data type of the field (e.g., string, boolean, array).
     * 
     * @return ConfigurationField The created configuration field instance.
     */
    protected function addField(string $name, string $fieldType, string $dataType): ConfigurationField {
        $field = new ConfigurationField($name, $fieldType, $dataType);
        $this->fields[] = $field;

        return $field;
    }
}