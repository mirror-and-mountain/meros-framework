<?php 

namespace MM\Meros\Services\Concerns;

use Closure;

use MM\Meros\Services\Contracts\FeatureSet;
use MM\Meros\Services\Registers\FeatureSets as FeatureSetsRegister;

use MM\Meros\Facades\FeatureSets;

trait HasFeatureSets {
    /**
     * Retrieves a feature set by its handle or the feature sets register.
     *
     * @param string       $handle Optional. The handle of the feature set to retrieve.
     * @param Closure|null $callback Optional. A callback to modify the feature set before returning it.
     *
     * @return FeatureSet|FeatureSetsRegister|null The requested feature set or a collection of all feature sets. Null if the requested feature set doesn't exist.
     */
    protected function featureSets(string $handle = '', ?Closure $callback = null): FeatureSet|FeatureSetsRegister|null {
        if (empty($handle)) {
            return FeatureSets::checkout($this->resolveAuthority()); // return register instance
        }

        else {
            return FeatureSets::get($handle, $this->resolveAuthority(), $callback);
        }
    }

    /**
     * Alias for the featureSets() method. Retrieves a feature set by its handle or the feature sets register.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return FeatureSet|FeatureSetsRegister|null
     */
    protected function feature_sets(string $handle = '', ?Closure $callback = null): FeatureSet|FeatureSetsRegister|null {
        return $this->featureSets($handle, $callback);
    }
}