<?php

namespace MM\Meros\Contracts\Features;

use Closure;

interface Storable extends Registrable, Makeable {
    /**
     * Adds a new data item to the container and returns the item instance.
     *
     * @param string|Closure|array $typeCallbackOrProps The type of the data item to add, a closure to configure the item, or an array of properties to pass to the item's constructor.
     * @param Closure|array        $callbackOrProps     An optional callback to modify the data item instance after creation, or an array of properties to pass to the item's constructor.
     * @param array                $props               An array of properties to pass to the item's constructor.
     *
     * @return StorableItem
     */
    public function add(string|Closure|array $typeCallbackOrProps, Closure|array $callbackOrProps = [], array $props = []): StorableItem;

    /**
     * Returns the name of the container.
     * 
     * @param bool $withPrefix Whether to include the prefix in the name (default: false).
     *
     * @return string
     */
    public function getName(bool $withPrefix = false): string;

    /**
     * Returns the value of a specific data item in the container.
     *
     * @param string $key     The key of the data item to retrieve.
     * @param bool   $refresh Whether to refresh the value from the source (default: false).
     *
     * @return mixed
     */
    public function getItemValue(string $key, bool $refresh = false): mixed;
}