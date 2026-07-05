<?php

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\Integration;
use MM\Meros\Services\Registers\Integrations as IntegrationsRegister;

use MM\Meros\Facades\Integrations;

trait HasIntegrations {
    /**
     * Retrieves an integration by handle or the integrations register.
     *
     * @param string $handle
     * @param Closure|null $callback
     *
     * @return Integration|IntegrationsRegister|null
     */
    protected function integrations(string $handle = '', ?Closure $callback = null): Integration|IntegrationsRegister|null {
        if (empty($handle)) {
            return Integrations::checkout($this);
        }

        return Integrations::checkout($this)->get($handle, $callback);
    }
}