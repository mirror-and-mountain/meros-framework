<?php 

namespace MM\Meros\App;

use MM\Meros\App\Framework;
use MM\Meros\App\Features\CoreInstallable;

use MM\Meros\App\Contracts\AssetsRegistrar;
use MM\Meros\App\Contracts\BlocksRegistrar;
use MM\Meros\App\Contracts\SettingsRegistrar;
use MM\Meros\App\Contracts\ComponentsRegistrar;
use MM\Meros\App\Contracts\InstallablesRegistrar;

use MM\Meros\App\Concerns\HasIdentity;
use MM\Meros\App\Concerns\HasPreferences;
use MM\Meros\App\Concerns\HasAssets;
use MM\Meros\App\Concerns\HasBlocks;
use MM\Meros\App\Concerns\HasComponents;
use MM\Meros\App\Concerns\HasInstallables;
use MM\Meros\App\Concerns\HasSettings;

use Illuminate\Support\Facades\Log;

abstract class FeatureProvider implements 
    AssetsRegistrar, 
    BlocksRegistrar, 
    SettingsRegistrar, 
    // ComponentsRegistrar, 
    InstallablesRegistrar 
{
    use HasIdentity,
        HasPreferences,
        HasAssets,
        HasBlocks,
        // HasComponents,
        HasInstallables,
        HasSettings;

    public function __construct(
        protected FeatureRegistry $registry,
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
            $this->initFramework();
            return;
        }

        // Configure
        $this->configure();

        // Initialise
        $this->initialise();
    }

    protected function configure(): void {
        // Intentionally left blank for child classes to override.
    }

    abstract protected function initialise(): void;

    /**
     * If theme has just been activated, this method hooks the installMeros()
     * method to setup the core migrations table.
     *
     * @return void
     */
    protected function initFramework(): void {
        if (!$this instanceof Framework ) {
            return;
        }

        add_action('after_switch_theme', function() {
            $this->installFramework(); // Setup action to run core installable on theme activation.
        });
    }

    /**
     * Installs the core Meros migrations table so that other feature providers
     * can run installables.
     *
     * @return bool Returns true on success, false on failure.
     */
    protected function installFramework(): bool {
        if (!$this instanceof Framework) {
            return false;
        }

        $migrationsPath = \trailingslashit(
            $this->path . $this->getPreference('migrations_path') . DIRECTORY_SEPARATOR . 'core'
        );

        $installable = $this->makeCoreInstallable([
            'path'   => $migrationsPath . '001_create_meros_migrations_table.php',

        ]);

        $installed = $installable->install();

        if ($installed !== true) {
            return false;
        }

        return true;
    }

    /**
     * Creates the core installable instance for the Meros framework.
     *
     * @param array $config The configuration array for the core installable.
     * 
     * @return CoreInstallable|null The created core installable instance, or null if it cannot be created.
     */
    private function makeCoreInstallable(array $config): CoreInstallable|null {
        if (! $this instanceof Framework) {
            return null;
        }

        return app(
            CoreInstallable::class, [
                'source' => $this,
            ]
        )->make($config);
    }
}