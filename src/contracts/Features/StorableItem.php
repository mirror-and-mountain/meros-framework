<?php

namespace MM\Meros\Contracts\Features;

interface StorableItem extends Registrable, Makeable {
    /**
     * Sets the container for this data item and returns the item instance.
     *
     * @param Storable $container
     *
     * @return static
     */
    public function container(Storable $container): static;

    /**
     * Returns the name of the item.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns the default value of the item.
     *
     * @return mixed
     */
    public function getDefault(): mixed;

    /**
     * Returns the value of the item, optionally refreshing it from the source.
     *
     * @param bool $refresh Whether to refresh the value from the source (default: false).
     *
     * @return mixed
     */
    public function getValue(bool $refresh = false): mixed;

    /**
     * Returns the data type of the item.
     *
     * @return string
     */
    public function getDataType(): string;

    /**
     * Returns the nested data type for array data items.
     *
     * @return string
     */
    public function getNestedDataType(): string;
}