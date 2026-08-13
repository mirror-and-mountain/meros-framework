<?php

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Collection;

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
            return Integrations::checkout($this->resolveAuthority());
        }

        return Integrations::get($handle, $this->resolveAuthority(), $callback);
    }

    /**
     * Retrieves all integrations for the current authority.
     *
     * @return Collection
     */
    final public function getIntegrations(): Collection {
        return Integrations::get('', $this->resolveAuthority());
    }
}