<?php 

namespace MM\Meros\App;

use MM\Meros\App\Framework;

use MM\Meros\App\Concerns\HasIdentity;
use MM\Meros\App\Concerns\HasSettings;
use MM\Meros\App\Concerns\HasPreferences;

abstract class FeatureProvider {
    private array $requiredServices = ['core'];

    // Additional concerns may be added by child classes as needed.
    use HasIdentity,
        HasPreferences,
        HasSettings;

    public function __construct(
        protected Registry $registry,
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

        // Configure
        $this->configure();

        if ($isFramework) {
            return;
        }
    }

    protected function configure(): void {
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