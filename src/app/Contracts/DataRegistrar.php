<?php 

namespace MM\Meros\App\Contracts;

use Closure;
use MM\Meros\App\Support\Helpers\DataBuilder;

interface DataRegistrar {
    /**
     * Returns a new DataBuilder instance scoped to the current feature and optional path.
     * 
     * @param Closure|null $callback Optional callback to configure the builder instance.
     *
     * @return DataBuilder A new DataBuilder instance for building nested settings or schema.
     */
    public function configure(?Closure $callback = null): DataBuilder;

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


}