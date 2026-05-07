<?php 

namespace MM\Meros\Services\Contracts;

use MM\Meros\App\Framework;

use MM\Meros\Services\Concerns\HasAssets;
use MM\Meros\Services\Concerns\HasBlocks;
use MM\Meros\Services\Concerns\HasFields;
use MM\Meros\Services\Concerns\HasInstallers;
use MM\Meros\Services\Concerns\HasIdentity;
use MM\Meros\Services\Concerns\HasSettings;
use MM\Meros\Services\Concerns\HasPreferences;

use MM\Meros\App\Context;
use MM\Meros\Facades\Context as ContextAccessor;

abstract class FeatureProvider {
    /**
     * An object containing several properties and methods that the feature provider
     * can utilise throughout its lifecycle.
     *
     * @var Context
     */
    final protected Context $context;

    use HasIdentity,
        HasPreferences,
        HasSettings,
        HasAssets,
        HasBlocks,
        HasFields,
        HasInstallers;

    public function __construct(
        string $name = '',
        string $path = '',
        string $uri  = ''
    ) {
        // Set context
        $this->context = ContextAccessor::get();
        
        // Check if the child class is the Framework class
        $isFramework = $this instanceof Framework;

        // Set identity
        $this->setIdentity($name, $path, $uri);

        if (!$this->identitySet) {
            return;
        }

        // Init preferences
        $this->initPreferences();

        // Init default settings container
        $this->settingsContainer('default');

        // Framework is booted from its service provider
        if ($isFramework) {return;}

        // Load
        $this->load();

        // Configure
        $this->configure();
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
}