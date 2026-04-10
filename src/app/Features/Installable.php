<?php 

namespace MM\Meros\App\Features;

use MM\Meros\App\Contracts\InstallablesRegistrar;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Theme;
use MM\Meros\App\Facades\Registry;
use MM\Meros\App\Facades\Framework;

use MM\Meros\App\Models\Migration;
use MM\Meros\App\Support\Admin\Migration as Runner;

class Installable extends Feature {
    /**
     * The type of the installable, e.g. 'Table', 'Data'.
     *
     * @var string
     */
    public string $type;

    /**
     * The subtype of the installable, e.g. 'create', 'update', 'add', 'remove'.
     *
     * @var string
     */
    public string $subtype;

    /**
     * The human-readable label for the installable.
     *
     * @var string
     */
    public string $label;

    /**
     * The file path to the migration file that defines the installable's operations.
     *
     * @var string
     */
    public string $path;

    /**
     * The runner object that will execute the install and uninstall operations. 
     * For migrations, this is typically an instance of a Laravel Migration class.
     *
     * @var object
     */
    public object $runner;

    /**
     * Indicates whether this installable is owned by the theme.
     *
     * @var boolean
     */
    public bool $isThemeInstallable;

    /**
     * The current batch ID if the item is in the process of being installed or uninstalled.
     *
     * @var string
     */
    public string $currentBatchId = '';

    /**
     * Indicates whether the item is installed.
     *
     * @var boolean
     */
    public bool $isInstalled = false;

    /**
     * The time that the item was installed if available.
     *
     * @var string
     */
    public string $installedTime = '';

    /**
     * A property to hold any error message that occurs during installation.
     *
     * @var string
     */
    public string $installationError = '';

    /**
     * A property to hold any error message that occurs during uninstallation.
     *
     * @var string
     */
    public string $uninstallationError = '';

    public function __construct(
        public InstallablesRegistrar $source
    ) {
        $this->setSchema();
    }

    /**
     * Creates an Installable instance from a config array and registers it.
     *
     * @param  array $config Configuration array for the installable.
     * 
     * @return self  An instance of the Installable feature.
     */
    public function make(array $config): self {
        $sanitizedConfig = $this->sanitizeConfig($config);
        if ($sanitizedConfig !== false) {
            $this->setPropertiesFromConfig($sanitizedConfig);

            $this->ready = true;
            
            if ($this->isInstalled()) {
                $this->installedTime = $this->getInstalledTime() ?? '';
                $this->isInstalled   = true;
            }
        }

        else {
            // Set a default handle for installation errors.
            $this->handle = $config['handle'] ?? $this->source->handle . '_undefined_installable_' . Str::random(5);
        }
    
        Registry::add('installables', $this);

        return $this;
    }

    /**
     * Set the configuration schema for the installable.
     * Note: This feature overrides the santizeConfig method, so the schema is here just for reference.
     *
     * @return void
     */
    protected function setSchema(): void {
        $this->configSchema = [
            'handle'  => ['type' => 'string', 'required' => true],
            'type'    => ['type' => 'string', 'required' => true],
            'subtype' => ['type' => 'string', 'required' => true],
            'label'   => ['type' => 'string', 'required' => true],
            'path'    => ['type' => 'string', 'required' => true],
            'runner'  => ['type' => 'object', 'required' => true],
            'is_theme_installable' => ['type' => 'boolean', 'required' => true],
        ];
    }

    /**
     * Overrides the base sanitizeConfig method to include validation specific to installables
     *
     * @param  array       $config
     * @param  array       $schema
     *
     * @return array|false
     */
    protected function sanitizeConfig(array $config, array $schema = []): array|false {
        $path = $config['path'] ?? '';
        if (! File::exists($path) || ! File::isFile($path)) {
            $this->error = "The specified path '{$path}' does not exist or is not a file.";
            return false;
        }

        $runner = include $path;

        if (! $runner instanceof Runner) {
            $this->error = "The file at '{$path}' does not return a valid Migration instance.";
            return false;
        }

        $handle = Str::beforeLast(basename($path), '.');

        $withoutTimestamp = preg_replace('/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', '', $handle);
        $subtype          = Str::before($withoutTimestamp, '_');

        if (!Str::contains($subtype, ['create', 'update', 'add', 'remove'])) {
            $this->error = "The subtype '{$subtype}' is not valid. It must be one of: create, update, add, remove.";
            return false;
        }

        $config = [
            'handle'  => $handle,
            'type'    => 'migration',
            'subtype' => $subtype,
            'label'   => Str::title(str_replace('_', ' ', $withoutTimestamp)),
            'path'    => $path,
            'runner'  => $runner,
            'is_theme_installable' => $this->source instanceof Theme
        ];

        return $config;
    }

    /**
     * Intentionally left blank as installables use this object's install() and uninstall() methods when called.
     *
     * @return void
     */
    final public function load(): void {
        // Do nothing...
    }

    /**
     * Attempts to install the item. On failure, sets the $installationError property with a descriptive message.
     *
     * @param  string $batchId An optional batch ID to group this installation with other install tasks. If not provided, a new ULID will be generated.
     *
     * @return bool Returns true on successful installation, false on failure.
     */
    public function install(string $batchId = ''): bool {
        if (! $this->ready) {
            $this->installationError = "The installable '{$this->handle}' is not ready for installation.";
            return false;
        }

        $canInstall = $this->canRunInstall();

        if ($canInstall !== true) {
            $this->installationError = $canInstall;
            return false;
        }

         if ($this->isInstalled()) {
            $this->installationError = "The installable '{$this->handle}' is already installed.";
            return false;
        }

        $this->currentBatchId = $batchId === '' ? Str::ulid() : $batchId;

        try {
            $this->runner->up($this->handle);
        } catch (\Exception $e) {
            $this->installationError = "An error occurred while installing '{$this->handle}': " . $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Attempts to uninstall the item. On failure, sets the $uninstallationError property with a descriptive message.
     *
     * @return bool Returns true on successful uninstallation, false on failure.
     */
    public function uninstall(): bool {
        if ( ! $this->ready ) {
            $this->uninstallationError = "The installable '{$this->handle}' is not ready for uninstallation.";
            return false;
        }

        $canUninstall = $this->canRunUninstall();

        if ($canUninstall !== true) {
            $this->uninstallationError = $canUninstall;
            return false;
        }

        if (! $this->isInstalled()) {
            $this->uninstallationError = "The installable '{$this->handle}' is not installed.";
            return false;
        }

        try {
            $this->runner->down($this->handle);
        } catch (\Exception $e) {
            $this->uninstallationError = "An error occurred while uninstalling '{$this->handle}': " . $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Checks if the item is installed by verifying whether a migration record exists for this item.
     * 
     * @return bool
     */
    public function isInstalled(): bool {
        if ( ! Framework::isServiceInstalled('core') ) {
            return false;
        }

        $record = Migration::where('handle', $this->handle)->first();
        return $record !== null;
    }

    /**
     * Returns the time that the item was installed if available.
     *
     * @return string|null
     */
    final public function getInstalledTime(): ?string {
        if ( ! Framework::isServiceInstalled('core') ) {
            return null;
        }

        $record = Migration::where('handle', $this->handle)->first();

        if ($record) {
            return $record->created_at->format('d-m-Y H:i:s');
        }

        return null;
    }

    /**
     * Checks if the installation process can be run in the current context.
     *
     * @return string|true Returns true if the process can be run, or a string error message if it cannot.
     */
    protected function canRunInstall(): string|true {
        // Check the context is right
        if (! is_admin() && ! app()->runningInConsole()) {
            return "The installation can only be performed in the admin area or via the command line.";
        }

        // Check core service installed and attempt to install if it isn't
        if (Framework::isServiceInstalled('core') !== true) {
            return 'The meros core service does not appear to be properly set up. Please run the core installation process first.';
        }
        
        // Check user has permissions
        if (! current_user_can( 'manage_options' )) {
            return 'You do not have sufficient permissions to perform this installation.';
        }

        return true;
    }

    /**
     * Checks if the uninstallation process can be run in the current context.
     *
     * @return string|true Returns true if the process can be run, or a string error message if it cannot.
     */
    protected function canRunUninstall(): string|true {
        // Check the context is right
        if (! is_admin() && ! app()->runningInConsole()) {
            return "The uninstallation can only be performed in the admin area or via the command line.";
        }

        // Check core service installed and attempt to install if it isn't
        if (Framework::isServiceInstalled('core') !== true) {
            return 'The meros core service does not appear to be properly set up.';
        }
        
        // Check user has permissions
        if (! current_user_can( 'manage_options' )) {
            return 'You do not have sufficient permissions to perform this uninstallation.';
        }

        return true;
    }
}

