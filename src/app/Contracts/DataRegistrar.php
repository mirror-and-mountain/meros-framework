<?php 

namespace MM\Meros\App\Contracts;

use MM\Meros\App\Support\DataBuilder;

interface DataRegistrar {
    /**
     * Returns a new DataBuilder instance scoped to the current feature and optional path.
     *
     * @return DataBuilder A new DataBuilder instance for building nested settings or schema.
     */
    public function define(): DataBuilder;

    /**
     * Adds a sub-item to the current object definition at the specified path.
     *
     * @param  string $path The dot-notated path for the sub-item relative to the current builder's path (e.g. 'blocks.my-block').
     * @param  string $name The name of the sub-item.
     * @param  string $type The type of the sub-item (e.g. 'string', 'integer', 'object').
     * 
     * @return self
     */
    public function addSubItem(string $path, string $name, string $type = ''): self;

    /** Chainable method to set the dot-notated path for the object definition.
     *
     * @param  string|null $path The dot-notated path to set for the object definition (e.g. 'blocks.my-block').
     * 
     * @return self Returns the current instance for method chaining.
     */
    public function path(?string $path): self;

    /**
     * Converts the object instance to a schema array for use in REST API registration.
     *
     * @return array
     */
    public function toSchema(): array;

    /**
     * Retrieves an array of field names defined in the current object definition.
     *
     * @return array An array of field names.
     */
    public function getFieldNames(): array;

    /**
     * Retrieves an array of input names for the fields defined in the current object definition, formatted for use in form inputs.
     *
     * @return array An array of input names corresponding to the defined fields.
     */
    public function getInputNames(): array;
}