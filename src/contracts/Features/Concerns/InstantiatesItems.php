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
     * Instantiates a single item from a class name and assigns it to a specified property.
     *
     * @param string $propertyKey              The property key that defines the class to be instantiated. The class will be replaced with the instantiated object.
     * @param string $requiredClassDefinition  The required class definition that the item must extend or implement.
     * @param array  $props                    An array of properties to pass to the item's constructor.
     *
     * @throws \LogicException When the specified property does not exist in the class or if the property is not a string. When the resolved register does not support the required methods.
     * @throws \InvalidArgumentException If the class does not extend or implement the required class definition.
     */
    final protected function instantiate(string $propertyKey, string $requiredClassDefinition, array $props = []): void {
        if (!property_exists($this, $propertyKey)) {
            throw new \LogicException("Property '{$propertyKey}' does not exist in class " . static::class);
        }

        $instantiationType = $this->resolveInstantiationType($propertyKey, $requiredClassDefinition);

        if ($instantiationType === false) {
            return; // No class to instantiate, exit early.
        }

        switch ($instantiationType) {
            case 'single_class':
                $featureClassOrAlias = $this->{$propertyKey};
                $this->{$propertyKey} = $this->makeItemFrom($featureClassOrAlias, $requiredClassDefinition, $props);

                break;

            case 'array_of_classes':
                foreach ($this->{$propertyKey} as $index => $featureClassOrAlias) {
                    $this->{$propertyKey}[$index] = $this->makeItemFrom($featureClassOrAlias, $requiredClassDefinition, $props);
                }
                break;

            case 'single_class_with_props':
                $featureClassOrAlias = $this->{$propertyKey}['class'];
                $providedProps = $this->{$propertyKey}['props'] ?? [];
                $this->{$propertyKey} = $this->makeItemFrom($featureClassOrAlias, $requiredClassDefinition, $providedProps);
                break;

            case 'array_of_classes_with_props':
                foreach ($this->{$propertyKey} as $index => $item) {
                    $featureClassOrAlias = $item['class'];
                    $providedProps = $item['props'] ?? [];
                    $this->{$propertyKey}[$index] = $this->makeItemFrom($featureClassOrAlias, $requiredClassDefinition, $providedProps);
                }
                break;
        }
    }

    /**
     * Resolves the type of instantiation required to instantiate an item or items in a specified property, 
     * determining whether the property holds a single class, an array of classes, or an array of classes with associated properties.
     *
     * @param string $propertyKey             The property key to check.
     * @param string $requiredClassDefinition The required class definition that the item must extend or implement.
     *
     * @return string The type of instantiation needed: 'single_class', 'array_of_classes', 'single_class_with_props', or 'array_of_classes_with_props'.
     * @throws \LogicException If the property does not exist or if the items type cannot be resolved.
     */
    private function resolveInstantiationType(string $propertyKey, string $requiredClassDefinition): string|false {
        if (!property_exists($this, $propertyKey)) {
            throw new \LogicException("Property '{$propertyKey}' does not exist in class " . static::class);
        }

        if ((is_array($this->{$propertyKey}) || is_string($this->{$propertyKey})) && empty($this->{$propertyKey})) {
            return false; // No instantiation needed if the property is empty.
        }

        if (!is_array($this->{$propertyKey}) && $this->{$propertyKey} instanceof $requiredClassDefinition) {
            return false; // No instantiation needed if the property is already an instance of the required class.
        }

        if (is_array($this->{$propertyKey}) && !empty($this->{$propertyKey}) && $this->{$propertyKey}[0] instanceof $requiredClassDefinition) {
            return false; // No instantiation needed if the property is an array of instances of the required class.
        }

        $value       = $this->{$propertyKey};
        $singleClass = is_string($value) && !empty($value);

        if ($singleClass) {
            return 'single_class';
        }

        $arrayOfClasses = is_array($value) && count($value) > 0 && is_string(reset($value));

        if ($arrayOfClasses) {
            return 'array_of_classes';
        }

        $singleItemWithProps = is_array($value) && is_string($value['class'] ?? null) && is_array($value['props'] ?? null) && count($value) === 2;

        if ($singleItemWithProps) {
            return 'single_class_with_props';
        }

        $firstItem = reset($value);
        $arrayOfItemsWithProps = is_array($value) && count($value) > 0 && is_array($firstItem) && is_string($firstItem['class'] ?? null) && is_array($firstItem['props'] ?? null);

        if ($arrayOfItemsWithProps) {
            return 'array_of_classes_with_props';
        }

        throw new \LogicException("Unable to resolve the items type for property '{$propertyKey}'.");
    }

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
        return $register->checkout($this->getProvider())->hasRegisteredFeature($featureClassOrAlias);
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
        return $register->get($featureClassOrAlias, $register->isPrivate() ? $this->getProvider() : null);
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