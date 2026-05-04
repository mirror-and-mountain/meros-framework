<?php 

namespace MM\Meros\Services\Registers\Interfaces;

interface Discovery {
    /**
     * Discovers and registers features based on the provider's configuration.
     *
     * @return self
     */
    public function discover(?string $path = null): self;
}