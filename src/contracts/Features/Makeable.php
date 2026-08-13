<?php

namespace MM\Meros\Contracts\Features;

use Closure;
use MM\Meros\Contracts\Providers\FeatureProvider;

interface Makeable extends FeatureDefinition {
    /**
     * Creates a new instance of the feature definition.
     *
     * @param FeatureProvider $provider        The provider that registered the feature.
     * @param Closure|array   $callbackOrProps An optional callback to modify the feature instance after creation, or an array of properties to be passed to the feature's constructor.
     * @param array           $props           An array of properties to be passed to the feature's constructor.
     * @param array           $context         An array of context data for the feature.
     *
     * @return static The newly created feature instance.
     */
    public static function __make(
        FeatureProvider $provider,
        Closure|array   $callbackOrProps = [], 
        array           $props = [],
        array           $context = []
    ): static;
}