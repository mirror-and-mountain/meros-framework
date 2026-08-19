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
     * @param string      $featureClass The class name of the feature to register.
     * @param string      $alias        An optional alias for the feature class.
     * @param string|null $onBehalfOf   An optional provider classname to register the feature on behalf of. If null, the current provider will be used.
     * 
     * @return static
     */
    final public function register(string $featureClass, string $alias = '', ?string $onBehalfOf = null): static {
        $this->ensureCheckout('register');
        $provider = $this->getProvider();

        if ($this->hasRegisteredFeatureClass($featureClass, null, false) && $featureClass !== $this->getContract()) {
            return $this; // Unique class is already registered, no need to register again.
        }

        if (!$this->hasCorrectDefinition($featureClass)) {
            throw new \InvalidArgumentException("Feature class '{$featureClass}' is not a valid subclass of '{$this->getContract()}'.");
        }

        $this->registeredFeatures[] = [
            'class'      => $featureClass,
            'alias'      => $alias,
            'provider'   => $provider,
            'onBehalfOf' => $onBehalfOf !== $provider::class ? $onBehalfOf : null,
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

        // If we've been given an alias, register the feature class with the alias before making the instance.
        if (is_string($callbackPropsOrAlias) && Str::contains($featureClassOrAlias, '\\')) {
            $givenAlias         = $callbackPropsOrAlias;
            $checkedOutProvider = $this->getProvider();

            $this->register($featureClassOrAlias, $givenAlias);
            $this->checkout($checkedOutProvider); // Re-checkout the original provider after registering the feature class with the alias.

            $callbackOrProps = $props;
            $props = [];
        } else {
            $callbackOrProps = $callbackPropsOrAlias;
        }

        $registeredFeature    = $this->getRegisteredFeature($featureClassOrAlias, null, false);
        $featureClass         = $registeredFeature ? $registeredFeature['class'] ?? null : null;
        $featureAlias         = $registeredFeature ? $registeredFeature['alias'] ?? '' : '';
        $registeringProvider  = $registeredFeature ? $registeredFeature['provider'] ?? null : null;
        $registeredOnBehalfOf = $registeredFeature ? $registeredFeature['onBehalfOf'] ?? null : null;

        if ($registeringProvider === null) {
            throw new \RuntimeException("Couldn't get registering provider when making instance of '{$featureClass}.' (" . static::class . ").");
        }

        $provider = $registeringProvider;
        if ($registeredOnBehalfOf !== null) {
            $provider = app()->make($registeredOnBehalfOf);
        }

        // See if we already have an instance setup with the alias as the identifier.
        if (!empty($featureAlias)) {
            $existingInstance = $this->getExistingInstance($featureAlias, $provider);

            if ($existingInstance !== null) {
                $this->checkin();
                return $existingInstance;
            }
        }

        // If we're not registered and the provided string doesn't look like a fully qualified class name, throw an exception.
        if ($featureClass === null && !Str::contains($featureClassOrAlias, '\\')) {
            $this->checkin();
            throw new \InvalidArgumentException("Feature class or alias '{$featureClassOrAlias}' has not been registered with this register (" . static::class . ").");
        } 
        // Else, if the provided string looks like a fully qualified class name, use it as the feature class and validate it.
        else if ($featureClass === null) {
            $featureClass = $featureClassOrAlias;

            if (!$this->hasCorrectDefinition($featureClass)) {
                $this->checkin();
                throw new \InvalidArgumentException("Feature class '{$featureClass}' is not a valid subclass of '{$this->getContract()}'.");
            }
        }

        $instance         = $featureClass::__make_from_registered($provider, $callbackOrProps, $props);
        $identifier       = $instance->getIdentifier();
        $existingInstance = $this->getExistingInstance($identifier, $provider);

        if ($existingInstance !== null) {
            $this->checkin();
            return $existingInstance;
        }

        if (empty($identifier) && !empty($featureAlias)) {
            $instance->setIdentifier($featureAlias);
        }

        $this->attachInstance($instance, $provider);
        $this->checkin();
        return $instance;
    }

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