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

use MM\Meros\Facades\Context;

abstract class FeatureProvider {

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
        // Check if the child class is the Framework class;
        $isFramework = $this instanceof Framework;

        // Set identity
        $this->setIdentity($name, $path, $uri);

        if (!$this->identitySet) {
            return;
        }

        // Init preferences
        $this->initPreferences();

        if ($isFramework && $this->isFeaturesAdminPage()) {
            $this->tables()->discover(); // Discover tables in this context so we can load installers if available.
            dd($this->tables());
        }

        // Framework is booted from its service provider
        if ($isFramework) {return;}

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
     * Determines if the current context is the features admin page.
     * Used to load installers in this context if available.
     *
     * @return boolean
     */
    private function isFeaturesAdminPage(): bool {
        $context = Context::get();

        if (!$context->isAdmin) {
            return false;
        }

        if ($context->adminScreen !== 'options-general.php') {
            return false;
        }

        $params = $context->params;

        if (!isset($params['page']) || $params['page'] !== 'meros-features') {
            return false;
        }

        if (!isset($params['tab'])) {
            return true;
        } 
        
        else if (in_array($params['tab'], ['theme', 'packages'])) {
            return true;
        }

        return false;
    }
}