<?php 

namespace MM\Meros\App\Contracts;

interface FeatureDefinition {
    /**
     * Makes an instance of the feature definition from the given configuration array.
     */
    public function make(array $config): self;

    /**
     * Loads the feature by hooking it into WordPress.
     */
    public function load(): void;
}