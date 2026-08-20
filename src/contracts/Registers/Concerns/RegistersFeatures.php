<?php 

namespace MM\Meros\Contracts\Registers\Concerns;

use Closure;
use MM\Meros\Contracts\Features\Makeable;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Registers\Maker;
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

    private string $preloadType = 'classname';

    private Registrable|string|null $preloadedItem = null;

    use Abstracts;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Sets the registers preload type to either 'classname' or 'instance'. This determines how 
     * the register will handle feature classes when passed to the preload() method.
     *
     * @param string $type
     *
     * @return static
     */
    final protected function preloadType(string $type): static {
        if (!in_array($type, ['classname', 'instance'])) {
            throw new \InvalidArgumentException("Invalid load type '{$type}' specified. Must be either 'classname' or 'instance'.");
        }

        $this->preloadType = $type;
        return $this;
    }

    // =========================================================================
    // Operations
    // =========================================================================

    final public function preload(string $featureClass): static|Registrable {
        $this->ensureCheckout('preload');

        if ($this->preloadType === 'classname') {
            $this->preloadedItem = $featureClass;
            return $this;
        }

        if (!$this->hasCorrectDefinition($featureClass)) {
            throw new \InvalidArgumentException("Feature class '{$featureClass}' is not a valid subclass of '{$this->getContract()}'.");
        }

        $this->preloadedItem = $this->makeFrom($featureClass);
        $this->checkin();
        return $this->preloadedItem;   
    }

    /**
     * Registers a feature class with the register for use later on.
     *
     * @param string      $featureClass The class name of the feature to register. Optional only if the register has been preloaded with a classname.
     * @param string      $alias        An optional alias for the feature class.
     * @param string|null $onBehalfOf   An optional provider classname to register the feature on behalf of. If null, the current provider will be used.
     * 
     * @return static
     */
    final public function register(string $featureClass = '', string $alias = '', ?string $onBehalfOf = null): static {
        $this->ensureCheckout('register');

        if ($this->preloadType === 'classname' && $this->preloadedItem !== null) {
            $alias        = $featureClass;
            $onBehalfOf   = $alias;
            $featureClass = $this->preloadedItem;
        } else if (empty($featureClass) && !is_string($this->preloadedItem)) {
            throw new \InvalidArgumentException("Feature class must be provided when registering a feature.");
        }

        $provider = $this->getProvider();

        if ($this->hasRegisteredFeatureClass($featureClass, null, false) && $featureClass !== $this->getContract()) {
            return $this; // Unique class is already registered, no need to register again.
        }

        if (!$this->hasCorrectDefinition($featureClass)) {
            throw new \InvalidArgumentException("Feature class '{$featureClass}' is not a valid subclass of '{$this->getContract()}'.");
        }

        $this->registeredFeatures[] = [
            'class'       => $featureClass,
            'alias'       => empty($alias) ? Str::snake(class_basename($featureClass)) : $alias,
            'provider'    => $provider,
            'onBehalfOf'  => $onBehalfOf !== $provider::class ? $onBehalfOf : null,
            'identifier'  => '',
            'initialised' => false,
        ];

        $this->checkin();
        return $this;
    }

    /**
     * Creates a new instance of the specified feature class, if it has been registered with this register.
     * 
     * @param string               $featureClassOrAlias  The class name or alias of the feature to create.
     * @param Closure|array|string $callbackPropsOrAlias An optional callback to modify the feature instance after creation, an array of properties to pass to the feature's constructor, or a string alias for the feature.
     * @param array                $props                An array of properties to pass to the feature's constructor.
     *
     * @return Registrable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature class has not been registered with this register.
     */
    final public function makeFrom(string $featureClassOrAlias, Closure|array|string $callbackPropsOrAlias = [], array $props = []): Registrable {
        $this->ensureCheckout('makeFrom');
        $givenAlias         = null;
        $checkedOutProvider = $this->getProvider();

        // If we've been given an alias, register the feature class with the alias before making the instance.
        if (is_string($callbackPropsOrAlias) && Str::contains($featureClassOrAlias, '\\')) {
            $givenAlias = $callbackPropsOrAlias;

            $this->register($featureClassOrAlias, $givenAlias);
            $this->checkout($checkedOutProvider); // Re-checkout the original provider after registering the feature class with the alias.

            $callbackOrProps = $props;
            $props = [];
        } else {
            $callbackOrProps = $callbackPropsOrAlias;
        }

        // Retrieve the registered feature information for the given class or alias, if it exists.
        $registeredFeature    = $this->getRegisteredFeature($featureClassOrAlias, null, false);
        $registered           = $registeredFeature !== null;
        $featureClass         = $registered ? $registeredFeature['class'] : null;
        $featureAlias         = $registered ? $registeredFeature['alias'] : '';
        $featureIdentifier    = $registered ? $registeredFeature['identifier'] : '';
        $registeringProvider  = $registered ? $registeredFeature['provider'] : null;
        $registeredOnBehalfOf = $registered ? $registeredFeature['onBehalfOf'] : null;

        // If we don't have a registering provider, throw an exception.
        if ($registeringProvider === null) {
            throw new \RuntimeException("Couldn't get registering provider when making instance of '{$featureClass}.' (" . static::class . ").");
        }

        // Determine the provider to use for creating the feature instance. If the feature was registered on behalf of another provider, use that provider instead.
        $provider = $registeringProvider;
        if ($registeredOnBehalfOf !== null) {
            $provider = app()->make($registeredOnBehalfOf);
        }

        // See if we already have an instance setup with the alias or the identifier.
        if (!empty($featureAlias) || !empty($featureIdentifier)) {
            // Prioritise the identifier over the alias when checking for an existing instance, as the identifier is unique to the instance, while the alias is not.
            $identifier = !empty($featureIdentifier) ? $featureIdentifier : $featureAlias;
            $existingInstance = $this->getExistingInstance($identifier, $provider);

            // If the feature identifier has been set, we should have an existing instance...
            if ($existingInstance !== null) {
                $this->checkin();
                return $existingInstance;
            }
        }

        // If we're not registered and the provided string doesn't look like a fully qualified class name, throw an exception.
        if (!$registered && $featureClass === null && !Str::contains($featureClassOrAlias, '\\')) {
            $this->checkin();
            throw new \InvalidArgumentException("Feature class or alias '{$featureClassOrAlias}' has not been registered with this register (" . static::class . ").");
        } 

        // Else, if the provided string looks like a fully qualified class name, use it as the feature class, validate and register it.
        else if (!$registered && $featureClass === null) {
            $featureClass = $featureClassOrAlias;

            if (!$this->hasCorrectDefinition($featureClass)) {
                $this->checkin();
                throw new \InvalidArgumentException("Feature class '{$featureClass}' is not a valid subclass of '{$this->getContract()}'.");
            }

           $this->register($featureClass, $givenAlias ?? '');
           $this->checkout($checkedOutProvider); // Re-checkout the original provider after registering the feature class with the alias.
        }

        // Instantiate the feature class
        $instance         = $featureClass::__make_from_registered($provider, $callbackOrProps, $props);
        $identifier       = $instance->getIdentifier();
        $existingInstance = $this->getExistingInstance($identifier, $provider);

        // Check again for an existing instance before committing to the register.
        if ($existingInstance !== null) {
            $this->checkin();
            return $existingInstance;
        }

        // If the instance doesn't have an identifier, set it to the alias if one was provided.
        if (empty($identifier) && (!empty($featureAlias) || $givenAlias !== null)) {
            $instance->setIdentifier($givenAlias ?? $featureAlias);
        }

        // Update the registered feature entry with the instance's identifier and mark it as initialised.
        $registeredEntryKey = collect($this->registeredFeatures)
            ->search(function ($feature) use ($featureClass, $featureAlias, $provider) {
                return $feature['class'] === $featureClass &&
                       $feature['alias'] === $featureAlias &&
                       $feature['provider'] === $provider;
            });

        if ($registeredEntryKey !== false) {
            $this->registeredFeatures[$registeredEntryKey]['identifier']  = $instance->getIdentifier();
            $this->registeredFeatures[$registeredEntryKey]['initialised'] = true;
        }

        $this->attachInstance($instance, $provider);
        $this->checkin();
        return $instance;
    }

    /**
     * Creates a new instance of the feature definition associated with this register.
     *
     * @param Closure|array   $callbackOrProps An optional callback to modify the feature instance after creation, or an array of properties to be passed to the feature's constructor.
     * @param array           $props           An array of properties to be passed to the feature's constructor.
     *
     * @return Makeable|Registrable The newly created feature instance.
     * @throws \InvalidArgumentException if the feature definition class is not makeable.
     */
    final public function make(Closure|array $callbackOrProps = [], array $props = []): Makeable|Registrable {
        $this->ensureCheckout('make');

        if ($this->preloadType === 'classname' && $this->preloadedItem !== null) {
            $featureClass = $this->preloadedItem;
            return $this->makeFrom($featureClass, $callbackOrProps, $props);
        }

        else if ($this instanceof Maker && method_exists($this, 'makeFeature')) {
            return $this->makeFeature($callbackOrProps, $props);
        }

        throw new \InvalidArgumentException("Feature class must be provided when making a feature instance.");
    }


    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Retrieves an existing instance of a feature by its identifier, if it exists and the register is set to use unique instances.
     *
     * @param string $identifier The identifier of the feature instance to retrieve.
     *
     * @return Registrable|null The existing feature instance if found, or null if not found or if unique instances are not enforced.
     */
    private function getExistingInstance(string $identifier, FeatureProvider $provider): Registrable|null {
        $instance = $this->get($identifier, $provider, false);

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
        $baseClass = $this->getContract();
        $classInfo = ClassInfo::get($featureClass);

        $correctClass  = $featureClass === $classInfo->name || $classInfo->extends($baseClass);
        $isRegistrable = method_exists($featureClass, '__make_from_registered');

        return $correctClass && $isRegistrable;
    }

    /**
     * Returns an array of feature classes that have been registered with this register.
     * 
     * @param FeatureProvider|null $provider An optional provider to filter the features by. Required if the register is private.
     * @param bool                 $checkin  Whether to check the register back in after retrieving the features.
     *
     * @return array
     */
    final public function getRegisteredFeatures(?FeatureProvider $provider = null, bool $checkin = true): array {
        if (empty($this->registeredFeatures)) {
            return $this->returnValue($checkin, []);
        }

        if ($this->isPrivate()) {
            $this->ensureCheckout('getRegisteredFeatures');
            $provider = $this->getProvider();
        }

        if ($provider !== null) {
            $features = collect($this->registeredFeatures)
                ->where(function ($feature) use ($provider) {
                    return $feature['provider'] === $provider || ($feature['onBehalfOf'] 
                        ? $provider instanceof $feature['onBehalfOf'] 
                        : false
                    );
                })
                ->toArray();

            return $this->returnValue($checkin, $features ?? []);
        }

        return $this->returnValue($checkin, $this->registeredFeatures);
    }

    /**
     * Returns the registered feature for a given alias or class name, if it exists.
     *
     * @param string               $featureClassOrAlias The class name or alias of the registered feature.
     * @param FeatureProvider|null $provider An optional provider to filter the features by.
     * @param bool                 $checkin Whether to check the register back in after retrieving the feature.
     *
     * @return array|null The registered feature if found, or null if not found.
     */
    final public function getRegisteredFeature(string $featureClassOrAlias, ?FeatureProvider $provider = null, bool $checkin = true): ?array {
        $features = $this->getRegisteredFeatures($provider, false);

        if (!empty($features)) {
            $looksLikeClass = Str::contains($featureClassOrAlias, '\\');
            $feature = collect($features)->firstWhere($looksLikeClass ? 'class' : 'alias', $featureClassOrAlias) ?? null;
            return $this->returnValue($checkin, $feature);
        }

        return $this->returnValue($checkin, null);
    }

    /**
     * Returns the registered feature class name for a given alias, if it exists.
     *
     * @param string               $featureClassOrAlias The class name or alias of the registered feature.
     * @param FeatureProvider|null $provider An optional provider to filter the features by.
     * @param bool                 $checkin Whether to check the register back in after retrieving the feature.
     *
     * @return string|null The registered feature class name if found, or null if not found.
     */
    final public function getRegisteredFeatureClass(string $featureClassOrAlias, ?FeatureProvider $provider = null, bool $checkin = true): ?string {
        $feature = $this->getRegisteredFeature($featureClassOrAlias, $provider, false);
        return $this->returnValue($checkin, $feature['class'] ?? null);
    }

    /**
     * Checks if a specific feature has been registered with this register.
     *
     * @param string               $featureClassOrAlias The class name or alias of the feature to check.
     * @param FeatureProvider|null $provider An optional provider to filter the features by.
     * @param bool                 $checkin Whether to check the register back in after checking for the feature.
     *
     * @return bool True if the feature class has been registered, false otherwise.
     */
    final public function hasRegisteredFeature(string $featureClassOrAlias, ?FeatureProvider $provider = null, bool $checkin = true): bool {
        $feature = $this->getRegisteredFeature($featureClassOrAlias, $provider, false);
        return $this->returnValue($checkin, $feature !== null);
    }

    /**
     * Checks if a specific feature class has been registered with this register.
     *
     * @param string               $featureClass The class name of the feature to check.
     * @param FeatureProvider|null $provider An optional provider to filter the features by.
     * @param bool                 $checkin Whether to check the register back in after checking for the feature.
     *
     * @return bool True if the feature class has been registered, false otherwise.
     */
    final public function hasRegisteredFeatureClass(string $featureClass, ?FeatureProvider $provider = null, bool $checkin = true): bool {
        $class = $this->getRegisteredFeatureClass($featureClass, $provider, false);
        return $this->returnValue($checkin, $class !== null);
    }
}