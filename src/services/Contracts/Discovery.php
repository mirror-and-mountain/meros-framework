<?php 

namespace MM\Meros\Services\Contracts;

interface Discovery {
    /**
     * Discovers and registers features based on the provider's configuration.
     *
     * @return void
     */
    public function discover(?string $path = null): void;
}