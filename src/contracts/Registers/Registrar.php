<?php 

namespace MM\Meros\Contracts\Registers;

use Closure;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Providers\FeatureProvider;

interface Registrar extends FeatureRegister {
    /**
     * Preloads a feature class for use with this register.
     *
     * @param string $featureClass
     *
     * @return static|Registrable
     */
    public function preload(string $featureClass): static|Registrable;

    /**
     * Registers a feature class with the register for use later on.
     *
     * @param string      $featureClass The class name of the feature to register. Optional only if the register has been preloaded with a classname.
     * @param string      $alias        An optional alias for the feature class.
     * @param string|null $onBehalfOf   An optional provider classname to register the feature on behalf of. If null, the current provider will be used.
     * 
     * @return static
     */
    public function register(string $featureClass = '', string $alias = '', ?string $onBehalfOf = null): static;

    
    /**
     * Creates a new instance of the specified feature class, if it has been registered with this register.
     * 
     * @param string               $featureClassOrAlias The class name or alias of the feature to create.
     * @param Closure|array|string $input               An optional callback to modify the feature instance after creation, an array of properties to pass to the feature's constructor, or a string alias for the feature. An 'onBehalfOf' provider can also be specified here as a string, if the feature is to be registered on behalf of another provider.
     * @param array                $props               An array of properties to pass to the feature's constructor.
     *
     * @return Registrable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature class has not been registered with this register.
     */
    public function makeFrom(string $featureClassOrAlias, Closure|array|string $input = [], array $props = []): Registrable;

    /**
     * Checks if a specific feature class has been registered with this register.
     *
     * @param string               $featureClassOrAlias The class name or alias of the feature to check.
     * @param FeatureProvider|null $provider            An optional provider to check against.
     * @param bool                 $checkin             Whether to check in the register after checking for the feature class.
     *
     * @return bool True if the feature class has been registered, false otherwise.
     */
    public function hasRegisteredFeature(string $featureClassOrAlias, ?FeatureProvider $provider = null, bool $checkin = true): bool;
}