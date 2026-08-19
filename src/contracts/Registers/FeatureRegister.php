<?php 

namespace MM\Meros\Contracts\Registers;

use Illuminate\Support\Collection;

use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\FeatureDefinition;

interface FeatureRegister {
    /**
     * Checks out the register to a specific FeatureProvider.
     *
     * @param string               $identifier The identifier of the feature to retrieve.
     * @param FeatureProvider|null $provider   An optional provider to retrieve the feature for.
     * @param bool                 $checkin    Whether to check the register back in after retrieving the feature.
     *
     * @return FeatureDefinition|null
     */
    public function get(string $identifier, ?FeatureProvider $provider = null, bool $checkin = true): FeatureDefinition|null;

    /**
     * Returns all features in the register, optionally filtered by a specific FeatureProvider.
     *
     * @param FeatureProvider|null $provider An optional provider to retrieve the features for.
     * @param bool                 $checkin  Whether to check the register back in after retrieving the features.
     *
     * @return Collection
     */
    public function all(?FeatureProvider $provider = null, bool $checkin = true): Collection;

    /**
     * Returns the instance of the register, allowing for method chaining.
     * 
     * @param FeatureProvider|null $provider An optional provider to check out the register to before returning the instance.
     *
     * @return static
     */
    public function instance(?FeatureProvider $provider = null): static;

    /**
     * Checks out the register to a specific FeatureProvider.
     *
     * @param FeatureProvider|null $provider An optional provider to check out the register to. If null is passed, the register will be checked-in (i.e., not checked-out to any provider).
     *
     * @return static
     */ 
    public function checkout(?FeatureProvider $provider = null): static;

    /**
     * Checks-in the register by removing the current FeatureProvider it is checked-out to, if any.
     *
     * @return static
     */
    public function checkin(): static;

    /**
     * Determines if the register is private, meaning it is associated with a specific FeatureProvider.
     *
     * @return bool True if the register is private, false otherwise.
     */
    public function isPrivate(): bool;
}