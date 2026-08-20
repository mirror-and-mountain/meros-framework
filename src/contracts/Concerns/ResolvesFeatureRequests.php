<?php

namespace MM\Meros\Contracts\Concerns;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Facades\Support\Registers;

trait ResolvesFeatureRequests {
    /**
     * Retrieves the current provider associated with the feature request.
     *
     * @return FeatureProvider
     */
    abstract public function getProvider(): FeatureProvider;

    /**
     * Retrieves the register for the feature class.
     *
     * @param string $requiredFeatureClass
     *
     * @return Register|null
     */
    final protected function getRegisterFor(string $requiredFeatureClass): ?Register {
        return Registers::getRegisterFor($requiredFeatureClass);
    }

    /**
     * Returns the facade class associated with a specific feature register, if any.
     *
     * @param string $requiredFeatureClass
     *
     * @return string|null
     */
    final protected function getFacadeFor(string $requiredFeatureClass): ?string {
        $register = $this->getRegisterFor($requiredFeatureClass);

        if ($register) {
            return $register->getFacade();
        }

        return null;
    }

    /**
     * Resolves a feature or register based on the required feature class and an optional name.
     *
     * @param string $requiredFeatureClass The class name of the required feature.
     * @param string $identifier Optional. The identifier of the specific feature to retrieve.
     *
     * @return Feature|Register|null The resolved feature or register, or null if the feature with the provided name is not found.
     *
     * @throws \RuntimeException If no register or facade is found for the required feature class, or if the register's definition does not match the required feature class.
     */
    final protected function resolveFeatureRequestFor(string $requiredFeatureClass, string $identifier = ''): Feature|Register|null {
        $register = $this->getRegisterFor($requiredFeatureClass);

        if ($register === null) {
            throw new \RuntimeException("No register found for the feature class: {$requiredFeatureClass}");
        }

        if ($register->getContract() !== $requiredFeatureClass) {
            throw new \RuntimeException("The register's definition does not match the required feature class: {$requiredFeatureClass}");
        }

        if (!empty($identifier)) {
            return $register->checkout($this->getProvider())->get($identifier);
        }

        return $register->checkout($this->getProvider());
    }
}