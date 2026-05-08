<?php 

namespace MM\Meros\Services\Concerns;

trait Discoverable {
    /**
     * Whether the item was discovered as opposed to manually registered.
     *
     * @var boolean
     */
    protected bool $wasDiscovered = false;
}