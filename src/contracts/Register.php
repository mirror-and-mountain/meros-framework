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
     * The class name of the feature definition that this register will create.
     *
     * @var string
     */
    private string $definitionClass = FeatureDefinition::class;

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
     * Sets the class name of the feature definition that this register will create.
     *
     * @param string $definitionClass The class name of the feature definition.
     *
     * @return void
     */
    final protected function definition(string $definitionClass): void {
        $this->definitionClass = $definitionClass;
    }

    /**
     * Returns the class name of the feature definition that this register will create.
     *
     * @return string
     */
    final public function getDefinition(): string {
        return $this->definitionClass;
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
     * @return FeatureProvider|null
     */
    final protected function getProvider(): ?FeatureProvider {
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
            $hasExisting = $this->has($handle, null, $provider);

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
     *
     * @return Collection
     * @throws \LogicException If the register is private and no provider is specified.
     */
    final public function all(?FeatureProvider $provider = null): Collection {
        if (($this->isPrivate && $provider !== null) || $provider !== null) {
            $features = $this->features->where('provider', $provider);
            return $features;
        }

        if ($this->isPrivate && $provider === null) {
            throw new \LogicException("Cannot retrieve all features from a private register without specifying the provider.");
        }
        
        return $this->features;
    }

    /**
     * Returns a specific feature by name.
     *
     * @param string               $name The name of the feature to retrieve.
     * @param FeatureProvider|null $provider An optional provider to retrieve the feature for, required if the register is private.
     *
     * @return FeatureDefinition|null
     * @throws \LogicException If the register is private and no provider is specified.
     */
    final public function get(string $name, ?FeatureProvider $provider = null): FeatureDefinition|null {
        $this->checkout($provider);
        
        if (($this->isPrivate && $provider !== null) || $provider !== null) {
            $feature = $this->features->where('provider', $provider)->where(function ($f) use ($name) {
                return $f->getIdentifier() === $name;
            })->first();

            return $feature;
        }

        if ($this->isPrivate && $provider === null) {
            throw new \LogicException("Cannot retrieve a feature by name from a private register without specifying the provider.");
        }

        return $this->features->firstWhere(function ($f) use ($name) {
            return $f->getIdentifier() === $name;
        });
    }

    /**
     * Checks if a feature with the given name exists in the register.
     *
     * @param string                 $name The name of the feature to check for.
     * @param FeatureDefinition|null $excludingFeature An optional feature to exclude from the check.
     * @param FeatureProvider|null   $provider An optional provider to check out the register to before checking for the feature.
     *
     * @return bool
     */
    final public function has(string $name, ?FeatureDefinition $excludingFeature = null, ?FeatureProvider $provider = null): bool {
        $feature = $this->get($name, $provider);

        if ($excludingFeature !== null && $feature === $excludingFeature) {
            return false;
        }

        return $feature !== null;
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
}