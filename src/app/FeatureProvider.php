<?php 

namespace MM\Meros\App;

use MM\Meros\App\Framework;

use MM\Meros\App\Contracts\AssetsRegistrar;
use MM\Meros\App\Contracts\BlocksRegistrar;
use MM\Meros\App\Contracts\SettingsRegistrar;
use MM\Meros\App\Contracts\InstallablesRegistrar;

use MM\Meros\App\Concerns\HasIdentity;
use MM\Meros\App\Concerns\HasPreferences;
use MM\Meros\App\Concerns\HasAssets;
use MM\Meros\App\Concerns\HasBlocks;
use MM\Meros\App\Concerns\HasInstallables;
use MM\Meros\App\Concerns\HasSettings;

abstract class FeatureProvider implements 
    AssetsRegistrar, 
    BlocksRegistrar, 
    SettingsRegistrar, 
    InstallablesRegistrar 
{
    private array $requiredServices = ['core'];

    use HasIdentity,
        HasPreferences,
        HasAssets,
        HasBlocks,
        HasInstallables,
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

        // Initialise
        $this->initialise();
    }

    protected function configure(): void {
        // Intentionally left blank for child classes to override.
    }

    abstract protected function initialise(): void;

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