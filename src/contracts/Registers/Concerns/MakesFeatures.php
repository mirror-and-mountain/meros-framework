<?php 

namespace MM\Meros\Contracts\Registers\Concerns;

use Closure;

use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Registers\Registrar;

trait MakesFeatures {
    use Abstracts;

    abstract public function register(string $featureClass, string $alias = '', bool $makeNow = false): static|Registrable;
    abstract public function hasRegisteredFeature(string $featureClassOrAlias): bool;

    /**
     * Creates a new instance of the feature definition associated with this register.
     *
     * @param Closure|array   $callbackOrProps An optional callback to modify the feature instance after creation, or an array of properties to be passed to the feature's constructor.
     * @param array           $props           An array of properties to be passed to the feature's constructor.
     *
     * @return Makeable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature definition class is not makeable.
     */
    final public function make(Closure|array $callbackOrProps = [], array $props = []): Makeable {
        $this->ensureCheckout('make');
        $provider = $this->getProvider();
        $featureClass = $this->getDefinition();

        if (!method_exists($featureClass, '__make')) {
            throw new \InvalidArgumentException("Feature class '{$featureClass}' is not makeable.");
        }

        $featureInstance = $featureClass::__make($provider, $callbackOrProps, $props);

        $this->attachInstance($featureInstance, $provider);

        if ($featureInstance instanceof Registrable && $this instanceof Registrar) {
            $identifier = $featureInstance->getIdentifier();
            if (!$this->hasRegisteredFeature($identifier)) {
                $this->register(get_class($featureInstance), $identifier);
            }
        }

        $this->checkin();
        return $featureInstance;
    }
}