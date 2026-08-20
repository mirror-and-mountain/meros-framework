<?php 

namespace MM\Meros\Contracts\Registers;

use Closure;
use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;

interface Maker extends FeatureRegister {
    /**
     * Creates a new instance of the feature definition associated with this register.
     *
     * @param Closure|array   $callbackOrProps An optional callback to modify the feature instance after creation, or an array of properties to be passed to the feature's constructor.
     * @param array           $props           An array of properties to be passed to the feature's constructor.
     *
     * @return Makeable|Registrable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature definition class is not makeable.
     */
    public function make(Closure|array $callbackOrProps = [], array $props = []): Makeable|Registrable;
}