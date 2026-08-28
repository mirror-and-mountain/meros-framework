<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Features\Integrations\Integration;
use MM\Meros\Registers\Data\Integrations;

trait ProvidesIntegrations {
    use Abstracts;

    /**
     * Retrieves a specific integration by its name or returns the integrations register.
     *
     * @param string $name Optional. The name of the integration to retrieve.
     * 
     * @return Integration|Integrations|null The requested integration or the integrations register.
     */
    final protected function integrations(string $name = ''): Integration|Integrations|null {
        return $this->resolveFeatureRequestFor(Integration::class, $name);
    }
}