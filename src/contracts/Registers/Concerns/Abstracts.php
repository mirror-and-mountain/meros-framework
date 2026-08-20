<?php 

namespace MM\Meros\Contracts\Registers\Concerns;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\FeatureDefinition;

trait Abstracts {
    /**
     * Attaches a feature instance to the register for tracking and management.
     *
     * @param FeatureDefinition $instance The feature instance to attach.
     * @param FeatureProvider   $provider The provider associated with the feature instance.
     *
     * @return void
     */
    abstract protected function attachInstance(FeatureDefinition $instance, FeatureProvider $provider): void;

    /**
     * Ensures that the register is checked out before performing an action.
     *
     * @param string $action
     *
     * @return void
     */
    abstract protected function ensureCheckout(string $action = ''): void;

    /**
     * Retrieves the provider the register is currently checked out to.
     *
     * @return FeatureProvider|null
     */
    abstract protected function getProvider(): ?FeatureProvider;

    /**
     * Helper to return a value and optionally check the register back in.
     *
     * @param boolean $checkin
     * @param mixed   $value
     *
     * @return mixed
     */
    abstract protected function returnValue(bool $checkin, mixed $value): mixed;

    /**
     * Checks out the register to a specific provider.
     *
     * @param FeatureProvider|null $provider The provider to check out the register to.
     *
     * @return static
     */
    abstract public function checkout(?FeatureProvider $provider = null): static;

    /**
     * Checks the register back in, releasing it from the current provider.
     *
     * @return static
     */
    abstract public function checkin(): static;

    /**
     * Determines if the register is currently checked out to a provider.
     *
     * @return bool
     */
    abstract public function isCheckedOut(): bool;

    /**
     * Determines if the register is private.
     *
     * @return bool
     */
    abstract public function isPrivate(): bool;

    /**
     * Determines if the register requires instances with unique identifiers.
     *
     * @return bool
     */
    abstract public function usesUniqueInstances(): bool;

    /**
     * Retrieves the contract (feature definition class) associated with this register.
     *
     * @return string
     */
    abstract public function getContract(): string;

    /**
     * Retrieves a feature instance by its identifier.
     *
     * @param string               $identifier The identifier of the feature instance.
     * @param FeatureProvider|null $provider   An optional provider to check against.
     * @param bool                 $checkin    Whether to check in the register after retrieving the feature instance.
     *
     * @return FeatureDefinition|null The feature instance if found, or null if not found.
     */
    abstract public function get(string $identifier, ?FeatureProvider $provider = null, bool $checkin = true): FeatureDefinition|null;

    /**
     * Checks if a feature instance with the specified identifier exists in the register.
     *
     * @param string               $identifier      The identifier of the feature instance.
     * @param Feature|null         $excludingFeature An optional feature instance to exclude from the check.
     * @param FeatureProvider|null $provider        An optional provider to check against.
     * @param bool                 $checkin         Whether to check in the register after checking for the feature instance.
     *
     * @return bool True if the feature instance exists, false otherwise.
     */
    abstract public function has(string $identifier, ?Feature $excludingFeature = null, ?FeatureProvider $provider = null, bool $checkin = true): bool;
}