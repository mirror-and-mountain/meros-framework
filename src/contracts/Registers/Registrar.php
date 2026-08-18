<?php 

namespace MM\Meros\Contracts\Registers;

use Closure;
use MM\Meros\Contracts\Features\Registrable;

interface Registrar extends FeatureRegister {
    /**
     * Registers a feature class with the register for use later on.
     *
     * @param string $featureClass The class name of the feature to register.
     * @param string $alias An optional alias for the feature class.
     * @param bool   $makeNow Whether to immediately create an instance of the feature after registration. $makeNow Whether to immediately create an instance of the feature after registration. A closure may also be passed to modify the feature instance after creation.
     * @param array  $props An array of properties to pass to the feature's constructor. Only used if $makeNow is true or a closure.
     *
     * @return static|Registrable The newly created feature instance if $makeNow is true or a Closure, otherwise the register instance.
     */
    public function register(string $featureClass, string $alias = '', bool|Closure $makeNow = false, array $props = []): static|Registrable;

    /**
     * Creates a new instance of the specified feature class, if it has been registered with this register.
     * 
     * @param string          $featureClassOrAlias The class name or alias of the feature to create.
     * @param Closure|array   $callbackOrProps     An optional callback to modify the feature instance after creation, or an array of properties to pass to the feature's constructor.
     * @param array           $props               An array of properties to pass to the feature's constructor.
     *
     * @return Registrable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature class has not been registered with this register.
     */
    public function makeFrom(string $featureClassOrAlias, Closure|array $callbackOrProps = [], array $props = []): Registrable;

    /**
     * Checks if a specific feature class has been registered with this register.
     *
     * @param string $featureClassOrAlias The class name or alias of the feature to check.
     *
     * @return bool True if the feature class has been registered, false otherwise.
     */
    public function hasRegisteredFeature(string $featureClassOrAlias): bool;
}