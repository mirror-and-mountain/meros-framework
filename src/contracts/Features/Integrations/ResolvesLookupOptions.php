<?php

namespace MM\Meros\Contracts\Features\Integrations;

interface ResolvesLookupOptions {
    /**
     * Returns a single record using the given object and record Id, if one exists.
     *
     * @param string $object
     * @param string $recordId
     *
     * @return object|null
     */
    public function find(string $object, string $recordId): object|null;

    /**
     * Adds a where argument to the currentQuery.
     *
     * @param string  $field
     * @param mixed   $operator
     * @param mixed   $value
     *
     * @return static
     */
    public function where(string $field, mixed $operator, mixed $value = null): static;

    /**
     * Sets the limit on the currentQuery.
     *
     * @param integer $limit
     *
     * @return static
     */
    public function limit(int $limit): static;

    /**
     * Executes an HTTP request based on the currentQuery property, returning a single record if found, 
     * or an array of records if any are returned based on the configured query.
     * 
     * @param bool|array $collect Whether to return the retrieved records as a collection (applicable only when the current query is looking for multiple records).
     *
     * @return object|array|null
     */
    public function get(bool|array $collect = true): object|array|null;

    /**
     * Retrieves an instance of the implementing object.
     *
     * @return static
     */
    public function getInstance(): static;
}