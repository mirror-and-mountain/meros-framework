<?php 

namespace MM\Meros\Contracts\Features\Concerns;

trait IsDiscoverable {
    /**
     * Indicates that the feature was discovered.
     *
     * @var boolean
     */
    public bool $wasDiscovered = false;
}