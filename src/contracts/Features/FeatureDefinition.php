<?php

namespace MM\Meros\Contracts\Features;

use MM\Meros\Contracts\Providers\FeatureProvider;

interface FeatureDefinition {
    /**
     * Returns the provider instance associated with the feature.
     *
     * @return FeatureProvider
     */
    public function getProvider(): FeatureProvider;

    /**
     * Returns the unique identifier of the feature.
     *
     * @return string
     */
    public function getIdentifier(): string;

    /**
     * Sets the unique identifier of the feature.
     *
     * @param string $identifier
     * @return static
     */
    public function setIdentifier(string $identifier): static;
}