<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Register;

trait Abstracts {
    /**
     * Resolves a feature or register based on the required feature class and an optional name.
     *
     * @param string $requiredFeatureClass The class name of the required feature.
     * @param string $name Optional. The name of the specific feature to retrieve.
     *
     * @return Feature|Register|null The resolved feature or register, or null if the feature with the provided name is not found.
     *
     * @throws \RuntimeException If no register or facade is found for the required feature class, or if the register's definition does not match the required feature class.
     */
    abstract protected function resolveRequestFor(string $requiredFeatureClass, string $name = ''): Feature|Register|null;

    /**
     * Retrieves the handle of the provider.
     * 
     * @param bool $slug Whether to return in slug-format. Defaults to false.
     *
     * @return string
     */
    abstract public function getHandle(bool $slug = false): string;

    /**
     * Retrieves the name of the provider.
     *
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Retrieves a provider's preference value based on the provided key.
     *
     * @param string $key The key of the preference to retrieve.
     * @param bool   $fullPath Optional. Whether to return the full path of the preference. Default is true.
     *
     * @return mixed The value of the preference, or null if not found.
     */
    abstract public function getPreference(string $key, bool $fullPath = true): mixed;
}