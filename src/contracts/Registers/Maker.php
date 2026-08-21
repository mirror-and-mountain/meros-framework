<?php 

namespace MM\Meros\Contracts\Registers;

use Closure;
use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;

interface Maker extends FeatureRegister {
    /**
     * Creates a new instance of the feature definition associated with this register.
     *
     * @param Closure|array|string $callbackPropsOrOnBehalfOf An optional callback to modify the feature instance after creation, an array of properties to be passed to the feature's constructor, or a string representing the provider on behalf of whom the feature is being created.
     * @param array                $props                     An array of properties to be passed to the feature's constructor.
     *
     * @return Makeable|Registrable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature definition class is not makeable.
     */
    public function make(Closure|array|string $callbackPropsOrOnBehalfOf = [], array $props = []): Makeable|Registrable;
}