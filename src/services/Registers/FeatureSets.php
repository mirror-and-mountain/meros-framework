<?php 

namespace MM\Meros\Services\Registers;

use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\FeatureSet;

class FeatureSets extends Register {
    protected string $identifier = 'handle';
    protected string $definition = FeatureSet::class;

    /**
     * Parses properties for the feature set's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return $props; // No special parsing needed for feature sets at this time.
    }
}