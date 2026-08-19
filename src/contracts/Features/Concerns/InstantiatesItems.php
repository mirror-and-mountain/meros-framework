<?php 

namespace MM\Meros\Contracts\Features\Concerns;

use Closure;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\Registrable;

use MM\Meros\Contracts\Registers\Registrar;
use MM\Meros\Facades\Support\Registers;

trait InstantiatesItems {
    abstract public function getProvider(): FeatureProvider;

    /**
     * Instantiates a single item from a class name using an appropriate register implementing the 'register' and 'makeFrom' methods.
     *
     * @param string        $featureClassOrAlias     The class name or alias of the item to instantiate.
     * @param string        $requiredClassDefinition The required class definition that the item must extend or implement.
     * @param Closure|array $callbackOrProps         An optional callback to modify the item instance after creation, or an array of properties to pass to the item's constructor.
     * @param array         $props                   An array of properties to pass to the item's constructor.
     *
     * @return Registrable The instantiated item.
     * @throws \InvalidArgumentException if the item class does not implement the Registrable interface or if the item class does not extend or implement the required class definition.
     */
    final protected function makeItemFrom(string $featureClassOrAlias, string $requiredClassDefinition, Closure|array $callbackOrProps = [], array $props = []): Registrable {
        $register = $this->resolveRegistrarRegister($requiredClassDefinition);

        $item = $register->checkout($this->getProvider())->makeFrom($featureClassOrAlias, $callbackOrProps, $props);

        if (!($item instanceof $requiredClassDefinition)) {
            throw new \InvalidArgumentException("The item '{$featureClassOrAlias}' must be an instance of '{$requiredClassDefinition}'.");
        }

        return $item;
    }

    /**
     * Checks if a specific item class or alias has been registered with the appropriate register implementing the 'register' and 'makeFrom' methods.
     *
     * @param string $featureClassOrAlias     The class name or alias of the item to check.
     * @param string $requiredClassDefinition The required class definition that the item must extend or implement.
     *
     * @return bool True if the item is registered, false otherwise.
     */
    final protected function itemIsRegistered(string $featureClassOrAlias, string $requiredClassDefinition): bool {
        $register = $this->resolveRegistrarRegister($requiredClassDefinition);
        $provider = $this->getProvider();
        return $register->checkout($provider)->hasRegisteredFeature($featureClassOrAlias);
    }

    /**
     * Retrieves a specific item instance from the appropriate register implementing the 'register' and 'makeFrom' methods.
     *
     * @param string $featureClassOrAlias     The class name or alias of the item to retrieve.
     * @param string $requiredClassDefinition The required class definition that the item must extend or implement.
     *
     * @return Registrable|null The retrieved item instance, or null if not found.
     */
    final protected function getItem(string $featureClassOrAlias, string $requiredClassDefinition): Registrable|null {
        $register = $this->resolveRegistrarRegister($requiredClassDefinition);
        $provider = $this->getProvider();
        return $register->checkout($provider)->get($featureClassOrAlias);
    }

    /**
     * Resolves the appropriate register for a given class definition, ensuring it implements the Registrar interface.
     *
     * @param string $requiredClassDefinition The required class definition that the item must extend or implement.
     *
     * @return Registrar The resolved register that implements the Registrar interface.
     * @throws \LogicException If no register is found or if the resolved register does not implement the Registrar interface.
     */
    private function resolveRegistrarRegister(string $requiredClassDefinition): Registrar {
        $register = Registers::getRegisterFor($requiredClassDefinition);

        if (!$register) {
            throw new \LogicException("No register found for class definition '{$requiredClassDefinition}'.");
        }

        if (!($register instanceof Registrar)) {
            throw new \LogicException("Resolved register for '{$requiredClassDefinition}' is not an instance of Registrar.");
        }

        return $register;
    }
}