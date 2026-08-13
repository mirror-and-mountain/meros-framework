<?php

namespace MM\Meros\Contracts\Features\Concerns;

use Closure;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Registers\Maker;
use MM\Meros\Facades\Support\Registers;

trait MakesItems {
    abstract public function getProvider(): FeatureProvider;

    /**
     * Instantiates a single item from a class name using an appropriate register implementing the 'make' method.
     *
     * @param string        $class           The class name of the item to instantiate.
     * @param Closure|array $callbackOrProps An optional callback to modify the item instance after creation, or an array of properties to pass to the item's constructor.
     * @param array         $props           An array of properties to pass to the item's constructor.
     *
     * @return Makeable The instantiated item.
     * @throws \InvalidArgumentException if the item class does not implement the Makeable interface or if the item class does not extend or implement the required class definition.
     */
    final protected function makeItem(string $class, Closure|array $callbackOrProps = [], array $props = []): Makeable {
        $register = $this->resolveMakerRegister($class);

        $item = $register->checkout($this->getProvider())->make($callbackOrProps, $props);

        if (!($item instanceof $class)) {
            throw new \InvalidArgumentException("The item '{$class}' must implement the Makeable interface.");
        }

        return $item;
    }

    /**
     * Resolves the appropriate register for a given class definition, ensuring it implements the Maker interface.
     *
     * @param string $requiredClassDefinition The required class definition that the item must extend or implement.
     *
     * @return Maker The resolved register that implements the Maker interface.
     * @throws \LogicException If no register is found or if the resolved register does not implement the Maker interface.
     */
    private function resolveMakerRegister(string $requiredClassDefinition): Maker {
        $register = Registers::getRegisterFor($requiredClassDefinition);

        if (!$register) {
            throw new \LogicException("No register found for class definition '{$requiredClassDefinition}'.");
        }

        if (!($register instanceof Maker)) {
            throw new \LogicException("Resolved register for '{$requiredClassDefinition}' is not an instance of Maker.");
        }

        return $register;
    }
}