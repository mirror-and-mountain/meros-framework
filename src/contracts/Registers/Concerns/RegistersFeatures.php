<?php 

namespace MM\Meros\Contracts\Registers\Concerns;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Support\ClassInfo;

trait RegistersFeatures {
    /**
     * An array of feature classes that have been registered with this register.
     *
     * @var array
     */
    private array $registeredFeatures = [];

    use Abstracts;

    /**
     * Registers a feature class with the register for use later on.
     *
     * @param string  $featureClass The class name of the feature to register.
     * @param string  $alias An optional alias for the feature class.
     * @param bool    $makeNow Whether to immediately create an instance of the feature after registration.
     *
     * @return static|Registrable The newly created feature instance if $makeNow is true, otherwise the register instance.
     * @throws \InvalidArgumentException if the feature class is not a valid subclass of the feature definition class associated with this register.
     */
    final public function register(string $featureClass, string $alias = '', bool $makeNow = false): static|Registrable {
        if ($this->hasRegisteredFeature($alias !== '' ? $alias : $featureClass)) {
            return $this; // Feature class is already registered, no need to register again.
        }

        if (!$this->hasCorrectDefinition($featureClass)) {
            throw new \InvalidArgumentException("Feature class '{$featureClass}' is not a valid subclass of '{$this->getDefinition()}'.");
        }

        if ($alias !== '') {
            $this->registeredFeatures[Str::snake($alias)] = $featureClass;
        } else {
            $this->registeredFeatures[] = $featureClass;
        }

        if ($makeNow) {
            return $this->makeFrom($alias !== '' ? $alias : $featureClass);
        }

        return $this;
    }

    /**
     * Attempts to create a new instance of the specified feature class, if it has been registered with this register.
     * If the feature class has not been registered, it will attempt to register it if it has the correct definition.
     * If registration fails, it will check for an existing instance using the provided alias.
     *
     * @param string          $featureClassOrAlias The class name or alias of the feature to create.
     * @param Closure|array   $callbackOrProps     An optional callback to modify the feature instance after creation, or an array of properties to pass to the feature's constructor.
     * @param array           $props               An array of properties to pass to the feature's constructor.
     *
     * @return Registrable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature class has not been registered with this register or cannot be located.
     */
    final public function makeFrom(string $featureClassOrAlias, Closure|array $callbackOrProps = [], array $props = []): Registrable {
        $this->ensureCheckout('makeFrom');
        $provider = $this->getProvider();
        
        // Try to find the registered feature class by alias or class name
        $resolvedFeature = $this->resolveRegisteredFeatureClass($featureClassOrAlias);

        // If the feature class is not found and the provided string looks like a fully qualified class name, attempt to register it
        if ($resolvedFeature === null && Str::contains($featureClassOrAlias, '\\')) {
            $resolvedFeature = $this->tryToRegisterFeature($featureClassOrAlias);

            // If the feature class is still not found after attempting to register, throw an exception
            if ($resolvedFeature === false) {
                throw new \InvalidArgumentException("Feature class '{$featureClassOrAlias}' has not been registered with this register (" . static::class . ").");
            }
        }

        // If feature class still not found, check for an existing instance using the alias.
        else if ($resolvedFeature === null) {
            $existingInstance = $this->getExistingInstanceByAlias($featureClassOrAlias, $this->isPrivate() ? $provider : null);

            if ($existingInstance !== null) {
                return $existingInstance;
            }

            // If the feature class is still not found, throw an exception
            throw new \InvalidArgumentException("Feature class or alias '{$featureClassOrAlias}' has not been registered with this register (" . static::class . ").");
        }

        // If the feature class is an array (from registration), extract the class and alias
        $featureClass = $resolvedFeature['class'];
        $alias = $resolvedFeature['alias'];

        // Check for an existing instance using the alias before creating a new one
        $existingInstance = $this->getExistingInstanceByAlias($alias, $this->isPrivate() ? $provider : null);

        if ($existingInstance !== null) {
            return $existingInstance;
        }

        // Setup a new instance and attach to the register
        $featureInstance = $featureClass::__make_from_registered($provider, $callbackOrProps, $props);

        if (empty($featureInstance->getIdentifier())) {
            $featureInstance->setIdentifier($alias !== '' ? $alias : Str::snake(class_basename($featureClass)));
        }
        
        $this->attachInstance($featureInstance, $provider);
        $this->checkin();
        return $featureInstance;
        
    }

    /**
     * Retrieves an existing instance of a feature by its alias, if it exists and the register is set to use unique instances.
     *
     * @param FeatureProvider|null $provider An optional provider to filter the features by. Required if the register is private.
     * @param string $alias The alias of the feature instance to retrieve.
     *
     * @return Registrable|null The existing feature instance if found, or null if not found or if unique instances are not enforced.
     */
    private function getExistingInstanceByAlias(string $alias, ?FeatureProvider $provider = null): Registrable|null {
        $instance = $this->get($alias, $provider);

        if ($instance instanceof Registrable && $this->usesUniqueInstances()) {
            return $instance;
        }

        return null;
    }

    /**
     * Checks if the provided feature class is a valid subclass of the feature definition class associated with this register.
     *
     * @param string $featureClass
     *
     * @return bool
     */
    private function hasCorrectDefinition(string $featureClass): bool {
        $baseClass = $this->getDefinition();
        $classInfo = ClassInfo::get($featureClass);

        $correctClass  = $featureClass === $classInfo->name || $classInfo->extends($baseClass);
        $isRegistrable = method_exists($featureClass, '__make_from_registered');

        return $correctClass && $isRegistrable;
    }

    /**
     * Resolves the registered feature class name from the provided class name or alias.
     *
     * @param string $featureClassOrAlias The class name or alias of the feature to resolve.
     *
     * @return array|null An array containing the resolved feature class name and alias if found, or null if not found.
     */
    private function resolveRegisteredFeatureClass(string $featureClassOrAlias): ?array {
        $class = $this->registeredFeatures[$featureClassOrAlias] ?? (in_array($featureClassOrAlias, $this->registeredFeatures) ? $featureClassOrAlias : null);

        if ($class === null) {
            return null;
        }

        return [
            'alias' => in_array($featureClassOrAlias, array_keys($this->registeredFeatures)) ? $featureClassOrAlias : Str::snake(class_basename($class)),
            'class' => $class
        ];
    }

    /**
     * Attempts to register a feature class with the register if it has the correct definition.
     *
     * @param string $featureClass
     *
     * @return array|false An array containing the registered class name and alias if successfully registered, false otherwise.
     */
    private function tryToRegisterFeature(string $featureClass): array|false {
        if ($this->hasCorrectDefinition($featureClass)) {
            $alias = Str::snake(class_basename($featureClass));
            $this->register($featureClass, $alias);

            return [
                'alias' => $alias,
                'class' => $featureClass
            ];
        }

        return false;
    }

    /**
     * Returns an array of feature classes that have been registered with this register.
     *
     * @return array
     */
    final public function getRegisteredFeatures(): array {
        return $this->registeredFeatures;
    }

    /**
     * Returns the registered feature class name for a given alias, if it exists.
     *
     * @param string $alias The alias of the registered feature.
     *
     * @return string|null The registered feature class name if found, or null if not found.
     */
    final public function getRegisteredFeature(string $alias): ?string {
        return $this->registeredFeatures[$alias] ?? null;
    }

    /**
     * Checks if a specific feature class has been registered with this register.
     *
     * @param string $featureClassOrAlias The class name or alias of the feature to check.
     *
     * @return bool True if the feature class has been registered, false otherwise.
     */
    final public function hasRegisteredFeature(string $featureClassOrAlias): bool {
        return isset($this->registeredFeatures[$featureClassOrAlias]) || in_array($featureClassOrAlias, $this->registeredFeatures);
    }
}