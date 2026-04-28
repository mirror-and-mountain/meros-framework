<?php 

namespace MM\Meros\Services\Contracts;

use Closure;

interface DataRegistrant {
    /**
     * Adds a sub-item to the current object definition at the specified path.
     *
     * @param string $name The name of the sub-item.
     * @param string $type The type of the sub-item (e.g. 'string', 'integer', 'object').
     * 
     * @return self
     */
    // public function add(string $name, string $type = ''): self;

    /** Chainable method to set the dot-notated path for the object definition.
     *
     * @param  string $path The dot-notated path to set for the object definition (e.g. 'blocks.my-block').
     * 
     * @return self Returns the current instance for method chaining.
     */
    public function path(string $path = ''): self;

    /**
     * Converts the object instance to a schema array for use in REST API registration.
     *
     * @return array
     */
    public function toSchema(): array;

    /**
     * Returns the data type of the current item (e.g. 'string', 'boolean' etc...).
     *
     * @return string|null
     */
    public function getDataType(): ?string;

    /**
     * Returns the data type of nested items inside an array-type item.
     *
     * @return string|null
     */
    public function getItemDataType(): ?string;

    /**
     * Adds a parent item to the current item definition, allowing for nested structures.
     *
     * @param DataRegistrant $parent
     *
     * @return self
     */
    public function parent(DataRegistrant $parent): self;

    /**
     * Sets the data type for the current item.
     *
     * @param string $type The data type to set (e.g. 'string', 'boolean', 'integer', 'array', 'object').
     * 
     * @return self
     */
    public function type(string $type): self;
}