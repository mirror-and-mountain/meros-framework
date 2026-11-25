<?php 

namespace MM\Meros\Registers\Data;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Integrations\Integration;

use MM\Meros\Contracts\Registers\Registrar;
use MM\Meros\Contracts\Registers\Concerns\RegistersFeatures;

use MM\Meros\Facades\Data\Integrations as IntegrationsFacade;

use MM\Meros\Contracts\Features\Integrations\Interfaces\Mailer;

class Integrations extends Register implements Registrar {
    use RegistersFeatures;

    protected function configure(): void {
        $this->unique(true);
        $this->bindAsSingletons(true);
        $this->contract(Integration::class);
        $this->facade(IntegrationsFacade::class);
    }

    /**
     * Resolve an integration by its registered identifier, class, or unique short class name.
     * Readiness is checked separately so callers can inspect unavailable integrations.
     */
    public function connect(string $name): Integration {
        $integrations = $this->all();
        $matches = $integrations->filter(fn ($integration) =>
            $integration->getIdentifier() === $name || $integration::class === $name
        );

        if ($matches->isEmpty()) {
            $matches = $integrations->filter(fn ($integration) =>
                Str::snake(class_basename($integration)) === $name
            );
        }

        if ($matches->count() !== 1) {
            throw new \InvalidArgumentException($matches->isEmpty()
                ? "Integration '{$name}' is not registered."
                : "Integration '{$name}' is ambiguous; use its registered identifier or class name.");
        }

        return $matches->first();
    }

    /**
     * Retrieves all registered mailer integrations.
     *
     * @param boolean $collect
     *
     * @return array|Collection
     */
    public function getMailers(bool $collect = false): array|Collection {
        $mailers = $this->all()->filter(fn ($integration) =>
            $integration instanceof Mailer
        );

        if ($collect) {
            return $mailers;
        }

        return $mailers->all();
    }

    /**
     * Retrieves a specific mailer integration by its registered identifier, class, or unique short class name.
     *
     * @param string $name
     *
     * @return Mailer
     */
    public function getMailer(string $name): Mailer {
        $integration = $this->connect($name);
        if (!$integration instanceof Mailer) {
            throw new \InvalidArgumentException("Integration '{$name}' is not a mailer.");
        }
        return $integration;
    }
}