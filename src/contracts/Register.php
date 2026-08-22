<?php 

namespace MM\Meros\Contracts;

use Illuminate\Support\Collection;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\FeatureDefinition;
use MM\Meros\Contracts\Registers\FeatureRegister;

use MM\Meros\Facades\Support\Registers;

abstract class Register implements FeatureRegister {
    /**
     * The feature provider that this register is checked out to, if any.
     *
     * @var FeatureProvider|null
     */
    private ?FeatureProvider $checkoutTo = null;

    /**
     * Indicates whether the register is private. 
     * Private registers must be checkedout to a Provider before 'get' methods can be called.
     *
     * @var boolean
     */
    private bool $isPrivate = false;

    /**
     * Indicates whether the register should use unique instances of features.
     *
     * @var boolean
     */
    private bool $uniqueInstances = false;

    /**
     * The default format of the feature's identifier. Can be 'slug' or 'snake'.
     *
     * @var string
     */
    private string $identifierFormat = 'snake';

    /**
     * The class name of the feature contract that this register will create.
     *
     * @var string
     */
    private string $contractClass = FeatureDefinition::class;

    /**
     * The class name of the facade associated with this register, if any.
     *
     * @var string
     */
    protected string $facadeClass = '';

    /**
     * A collection of instantiated features associated with this register.
     *
     * @var Collection
     */
    private Collection $features;

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Constructor for the Register class.
     */
    final public function __construct() {
        $this->features = collect();
        Registers::add($this);
        $this->configure();
    }

    protected function configure(): void {
        // This method can be overridden by subclasses to provide additional configuration.
    }

    /**
     * Sets the format of the feature's identifier. Can be 'slug' or 'snake'.
     *
     * @param string $format
     *
     * @return void
     */
    final protected function identifierFormat(string $format): void {
        if (!in_array($format, ['slug', 'snake'])) {
            throw new \InvalidArgumentException("Invalid identifier format '{$format}' specified for register (" . static::class . "). Valid formats are 'slug' and 'snake'.");
        }

        $this->identifierFormat = $format;
    }

    /**
     * Returns the format of the feature's identifier. Can be 'slug' or 'snake'.
     *
     * @return string
     */
    final protected function getIdentifierFormat(): string {
        return $this->identifierFormat;
    }

    /**
     * Sets the register as private or public.
     *
     * @param bool $isPrivate Whether the register should be private.
     *
     * @return void
     */
    final protected function private(bool $isPrivate = true): void {
        $this->isPrivate = $isPrivate;
    }

    /**
     * Returns whether the register is private.
     *
     * @return bool
     */
    final public function isPrivate(): bool {
        return $this->isPrivate;
    }

    /**
     * Sets whether the register should use unique instances of features.
     *
     * @param boolean $uniqueInstances Whether the register should use unique instances of features.
     *
     * @return void
     */
    final protected function unique(bool $uniqueInstances = true): void {
        $this->uniqueInstances = $uniqueInstances;
    }

    /**
     * Returns whether the register uses unique instances of features.
     *
     * @return bool
     */
    final public function usesUniqueInstances(): bool {
        return $this->uniqueInstances;
    }

    /**
     * Sets the class name of the feature contract that this register will create.
     *
     * @param string $contractClass The class name of the feature contract.
     *
     * @return void
     */
    final protected function contract(string $contractClass): void {
        $this->contractClass = $contractClass;
    }

    /**
     * Returns the class name of the feature contract that this register will create.
     *
     * @return string
     */
    final public function getContract(): string {
        return $this->contractClass;
    }

    /**
     * Sets the class name of the facade associated with this register.
     *
     * @param string $facadeClass
     *
     * @return void
     */
    final protected function facade(string $facadeClass): void {
        $this->facadeClass = $facadeClass;
    }

    /**
     * Returns the class name of the facade associated with this register, if any.
     *
     * @return string|null
     */
    final public function getFacade(): ?string {
        if ($this->facadeClass && class_exists($this->facadeClass)) {
            return $this->facadeClass;
        }

        return null;
    }

    // =========================================================================
    // Provider Management
    // =========================================================================

    /**
     * Returns the FeatureProvider the register is currently checked-out to, if any.
     *
     * @return FeatureProvider
     * @throws \LogicException If the register is not checked-out to any FeatureProvider.
     */
    final protected function getProvider(): FeatureProvider {
        if ($this->checkoutTo === null) {
            throw new \LogicException("Cannot retrieve the provider for register (" . static::class . ") because it is not checked-out to any FeatureProvider.");
        }

        return $this->checkoutTo;
    }

    /**
     * Returns whether the register is currently checked-out to a FeatureProvider.
     *
     * @return boolean
     */
    final public function isCheckedOut(): bool {
        return $this->checkoutTo !== null;
    }

    /**
     * Checks-out the register to a specific FeatureProvider.
     *
     * @param FeatureProvider|null $provider The FeatureProvider to check-out the register to. If null is passed, the register will be checked-in (i.e., not checked-out to any provider).
     *
     * @return static
     */
    final public function checkout(?FeatureProvider $provider = null): static {
        $this->checkoutTo = $provider;
        return $this;
    }

    /**
     * Checks-in the register by removing the current FeatureProvider it is checked-out to, if any.
     *
     * @return static
     */
    final public function checkin(): static {
        $this->checkoutTo = null;
        return $this;
    }

    /**
     * Ensures that the register is checked-out to a FeatureProvider before performing an action.
     *
     * @param string $action The action being performed, used for error messaging.
     *
     * @return void
     * @throws \LogicException If the register is not checked-out to a FeatureProvider.
     */
    final protected function ensureCheckout(string $action = ''): void {
        if (!$this->isCheckedOut()) {
            throw new \LogicException("Cannot call " . ($action ?? 'method') . " in register (" . static::class . ") because it is not checked-out to a FeatureProvider.");
        }
    }

    // =========================================================================
    // Feature Management
    // =========================================================================

    /**
     * Attaches an instantiated feature to the register.
     *
     * @param FeatureDefinition $feature The feature instance to attach.
     * @param FeatureProvider   $provider The provider to which the feature is associated.
     *
     * @return void
     * @throws \LogicException If the register is set to use unique instances and a feature with the same handle is already attached.
     */
    final protected function attachInstance(FeatureDefinition $feature, FeatureProvider $provider): void {
        if ($this->usesUniqueInstances()) {
            $handle = $feature->getIdentifier();
            $hasExisting = $this->has($handle, null, $provider, false);

            if ($hasExisting) {
                throw new \LogicException("A feature with handle '{$feature->getIdentifier()}' is already attached to this register (" . static::class . ") and the register is set to use unique instances.");
            }
        }

        $this->features->push($feature);
    }

    /**
     * Returns a collection of instantiated features associated with this register.
     * 
     * @param FeatureProvider|null $provider An optional provider to filter the features by. Required if the register is private.
     * @param bool                 $checkin Whether to check the register back in after retrieving the features.
     *
     * @return Collection
     * @throws \LogicException If the register is private and no provider is specified.
     */
    final public function all(?FeatureProvider $provider = null, bool $checkin = true): Collection {
        if ($this->isPrivate()) {
            $this->ensureCheckout('all');
            $provider = $this->getProvider();
        }

        if ($provider !== null) {
            $features = $this->features->where(function ($f) use ($provider) {
                return $f->getProvider() === $provider;
            });

            return $this->returnValue($checkin, $features);
        }

        return $this->returnValue($checkin, $this->features);
    }

    /**
     * Returns a specific feature by name.
     *
     * @param string               $identifier The identifier of the feature to retrieve.
     * @param FeatureProvider|null $provider An optional provider to retrieve the feature for.
     * @param bool                 $checkin Whether to check the register back in after retrieving the feature.
     *
     * @return FeatureDefinition|null
     * @throws \LogicException If the register is private and no provider is specified.
     */
    final public function get(string $identifier, ?FeatureProvider $provider = null, bool $checkin = true): FeatureDefinition|null {
        if ($this->isPrivate()) {
            $this->ensureCheckout('get');
            $provider = $this->getProvider();
        }
        
        if ($provider !== null) {
            $feature = $this->getFeatures()->where(function ($f) use ($provider) {
                return $f->getProvider() === $provider;
            })->where(function ($f) use ($identifier) {
                return $f->getIdentifier() === $identifier;
            })->first();

            return $this->returnValue($checkin, $feature);
        }

        $feature = $this->getFeatures()->firstWhere(function ($f) use ($identifier) {
            return $f->getIdentifier() === $identifier;
        });

        return $this->returnValue($checkin, $feature);
    }

    /**
     * Checks if a feature with the given name exists in the register.
     *
     * @param string                 $name The name of the feature to check for.
     * @param FeatureDefinition|null $excludingFeature An optional feature to exclude from the check.
     * @param FeatureProvider|null   $provider An optional provider to retrieve the features for.
     * @param bool                   $checkin Whether to check the register back in after checking for the feature.
     *
     * @return bool
     */
    final public function has(string $name, ?FeatureDefinition $excludingFeature = null, ?FeatureProvider $provider = null, bool $checkin = true): bool {
        $feature = $this->get($name, $provider, $checkin);

        if ($excludingFeature !== null && $feature === $excludingFeature) {
            return $this->returnValue($checkin, false);
        }

        return $this->returnValue($checkin, $feature !== null);
    }

    /**
     * Helper to return a requested value and check the register back in if needed.
     *
     * @param boolean $checkin
     * @param mixed   $value
     *
     * @return mixed
     */
    final protected function returnValue(bool $checkin, mixed $value): mixed {
        if ($checkin && $this->isCheckedOut()) {
            $this->checkin();
        }

        return $value;
    }


    // =========================================================================
    // Instance Retrieval
    // =========================================================================

    /**
     * Returns the instance of the register, allowing for method chaining.
     * 
     * @param FeatureProvider|null $provider An optional provider to check out the register to before returning the instance.
     *
     * @return static
     */
    final public function instance(?FeatureProvider $provider = null): static {
        return $this->checkout($provider);
    }

    /**
     * Internal method to retrieve the collection of features associated with this register.
     *
     * @return Collection
     */
    final protected function getFeatures(): Collection {
        return $this->features;
    }
}