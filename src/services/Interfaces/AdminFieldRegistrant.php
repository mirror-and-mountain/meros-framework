<?php

namespace MM\Meros\Services\Interfaces;

use Closure;
use MM\Meros\Services\Contracts\Forms\Field;

interface AdminFieldRegistrant {
    /**
     * Adds a field to the registrant.
     *
     * @param string|null  $type
     * @param Closure|null $callback
     * @param array        $args Optional additional args for concrete implementations.
     *
     * @return Field
     */
    public function field(?string $type = null, ?Closure $callback = null, array $args = []): Field;
    
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