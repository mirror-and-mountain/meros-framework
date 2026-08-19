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
     * @param string $format The format of the identifier to return. Can be 'default', 'slug', or 'snake'. Defaults to 'default'.
     *
     * @return string
     */
    public function getIdentifier(string $format = 'default'): string;

    /**
     * Sets the unique identifier of the feature.
     *
     * @param string $identifier
     * @param bool   $returnValue Whether to return the identifier value instead of the feature instance. Defaults to true.
     * 
     * @return string|static The feature instance or the identifier value, depending on the $returnValue parameter.
     */
    public function setIdentifier(string $identifier, bool $returnValue = true): string|static;
}