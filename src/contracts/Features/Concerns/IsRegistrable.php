<?php 

namespace MM\Meros\Contracts\Features\Concerns;

use Closure;
use MM\Meros\Contracts\Providers\FeatureProvider;

trait IsRegistrable {
    /**
     * Creates a new instance of the feature using the provided provider, callback or properties, and context.
     *
     * @param FeatureProvider $provider        The provider that registered the feature.
     * @param Closure|array   $callbackOrProps An optional callback to modify the feature instance after creation, or an array of properties to be passed to the 'passedProps' property of the feature instance.
     * @param array           $props           An array of properties to be passed to the 'passedProps' property of the feature instance.
     * @param array           $context         An array of context data for the feature.
     *
     * @return static
     */
    final public static function __make_from_registered(
        FeatureProvider $provider, 
        Closure|array   $callbackOrProps = [], 
        array           $props = [], 
        array           $context = []
    ): static {
        $instance = new static(
            $provider,
            $callbackOrProps,
            $props,
            array_merge($context, ['creation_method' => 'made_from_class'])
        );

        return $instance;
    }
}