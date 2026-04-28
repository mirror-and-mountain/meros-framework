<?php 

namespace MM\Meros\Services\Contracts;

use MM\Meros\App\Framework;

use MM\Meros\Services\Concerns\HasAssets;
use MM\Meros\Services\Concerns\HasBlocks;
use MM\Meros\Services\Concerns\HasFields;
use MM\Meros\Services\Concerns\HasInstallables;
use MM\Meros\Services\Concerns\HasIdentity;
use MM\Meros\Services\Concerns\HasSettings;
use MM\Meros\Services\Concerns\HasPreferences;

abstract class FeatureProvider {
    private array $requiredServices = ['core'];

    // Additional concerns may be added by child classes as needed.
    use HasIdentity,
        HasPreferences,
        HasSettings,
        HasAssets,
        HasBlocks,
        HasFields,
        HasInstallables;

    public function __construct(
        string $name = '',
        string $path = '',
        string $uri  = ''
    ) {
        // Check if the child class is the Framework class;
        $isFramework = $this instanceof Framework;

        // Set identity
        $this->setIdentity($name, $path, $uri);

        if (! $this->identitySet) {
            return;
        }

        // Init preferences
        $this->initPreferences();

        if ($isFramework) {
            return;
        }

        // Load
        $this->load();

        // Configure
        $this->configure();

        // After configuration
        $this->loaded();
    }

    protected function load(): void {
        // Intentionally left blank for child classes to override.
    }

    protected function configure(): void {
        // Intentionally left blank for child classes to override.
    }

    protected function loaded(): void {
        // Intentionally left blank for child classes to override.
    }

    /**
     * Adds a required service to the feature provider's requirements.
     *
     * @param string $service The name of the required service.
     * @return void
     */
    protected function require(string $service): void {
        if (!in_array($service, $this->requiredServices)) {
            $this->requiredServices[] = $service;
        }
    }

    /**
     * Removes a required service from the feature provider's requirements.
     *
     * @param string $service The name of the service to remove from requirements.
     * @return void
     */
    protected function removeRequirement(string $service): void {
        $this->requiredServices = array_filter(
            $this->requiredServices,
            fn($s) => $s !== $service
        );
    }

    /**
      * Returns an array of the feature provider's required services.
      *
      * @return array An array of required service names.
      */
    protected function getRequirements(): array {
        return $this->requiredServices;
    }
}