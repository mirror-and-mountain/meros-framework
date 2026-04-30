<?php

namespace MM\Meros\Services\Contracts\Interfaces;

use MM\Meros\Services\Contracts\Elements\Field;

interface AdminFieldRegistrant {
    /**
     * Adds a field to the registrant.
     *
     * @param Field|string|null $type
     * @param array       $props
     * @param array       $args
     *
     * @return Field
     */
    public function field(
        Field|string|null $type  = null,
        array             $props = [],
        array             $args  = []
    ): Field;
    
    /**
     * Retrieves the field ID from the registrant.
     *
     * @return string
     */
    public function getID(): string;

    /**
     * Retrieves the field name from the registrant.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Retrieves the field label from the registrant.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Retrieves the field description from the registrant.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Retrieves the field value from the registrant.
     *
     * @return mixed
     */
    public function getValue(): mixed;

    /**
     * Retrieves the field default value from the registrant.
     *
     * @return mixed
     */
    public function getDefault(): mixed;

    /**
     * Retrieves the names of sub-items from the registrant.
     *
     * @return array
     */
    public function getItemNames(): array;

    /**
     * Retrieves the names of sub-fields from the registrant.
     *
     * @return array
     */
    public function getFieldNames(): array;

    /**
     * Retrieves the labels of sub-items from the registrant.
     *
     * @return array
     */
    public function getItemLabels(): array;

    /**
     * Retrieves a specific sub-item by name from the registrant.
     *
     * @return AdminFieldRegistrant|null
     */
    public function getItemByName(string $name): ?AdminFieldRegistrant;
}