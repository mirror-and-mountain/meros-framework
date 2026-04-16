<?php 

namespace MM\Meros\App;

use MM\Meros\App\Framework;

use MM\Meros\App\Support\Helpers\Discover;

use MM\Meros\App\Concerns\HasAssets;
use MM\Meros\App\Concerns\HasBlocks;
use MM\Meros\App\Concerns\HasInstallables;
use MM\Meros\App\Concerns\HasIdentity;
use MM\Meros\App\Concerns\HasSettings;
use MM\Meros\App\Concerns\HasPreferences;

use MM\Meros\App\Support\Registry;

abstract class FeatureProvider {
    private array    $requiredServices = ['core'];
    private Discover $discover;

    // Additional concerns may be added by child classes as needed.
    use HasIdentity,
        HasPreferences,
        HasSettings,
        HasAssets,
        HasBlocks,
        HasInstallables;

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

    /**
     * Returns an instance of the Discover class for discovering assets/blocks
     *
     * @return Discover
     */
    protected function discover(): Discover {
        if (!isset($this->discover)) {
            $this->discover = app(Discover::class, ['source' => $this]);
        }
        return $this->discover;
    }

    /**
     * Returns the item's registry instance.
     * 
     * @return Registry
     */
    public function registry(): Registry {
        return $this->registry;
    }
}