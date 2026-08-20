<?php

namespace MM\Meros\Contracts;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Concerns\ResolvesFeatureRequests;

abstract class Orchestrator {
    /**
     * The feature provider instance used by the orchestrator.
     *
     * @var FeatureProvider
     */
    private FeatureProvider $provider;

    use ResolvesFeatureRequests;

    /**
     * Constructs a new instance of the orchestrator with the specified feature provider.
     *
     * @param FeatureProvider $provider The feature provider to be used by the orchestrator.
     */
    private function __construct(FeatureProvider $provider) {
        $this->provider = $provider;
        $this->configure();
    }

    /**
     * Creates a new instance of the orchestrator with the specified feature provider.
     *
     * @param FeatureProvider $provider The feature provider to be used by the orchestrator.
     *
     * @return static A new instance of the orchestrator.
     */
    final public static function create(FeatureProvider $provider): static {
        return new static($provider);
    }

    /**
     * Retrieves the feature provider associated with the orchestrator.
     *
     * @return FeatureProvider
     */
    final public function getProvider(): FeatureProvider {
        return $this->provider;
    }

    /**
     * This method is intended to be overridden by subclasses to provide additional configuration logic.
     *
     * @return void
     */
    protected function configure(): void {
        // This method can be overridden by subclasses to provide additional configuration logic.
    }
}