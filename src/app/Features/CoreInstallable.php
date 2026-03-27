<?php 

namespace MM\Meros\App\Features;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use MM\Meros\App\Models\Migration;

use Illuminate\Support\Facades\Log;

class CoreInstallable extends Installable {
    /**
     * Checks if the item is installed by verifying whether a migration record exists for this item.
     * 
     * @return bool
     */
    public function isInstalled(): bool {
        if (! Schema::hasTable('meros_migrations')) {
            return false;
        }

        $record = Migration::where('handle', $this->handle)->first();
        return $record !== null;
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

        try {
            $this->runner->up();

            $record = Migration::create([
                'source'         => $this->source->handle,
                'type'           => $this->type,
                'subtype'        => $this->subtype,
                'label'          => $this->label,
                'handle'         => $this->handle,
                'path_reference' => $this->path,
                'batch_id'       => $batchId === '' ? Str::ulid() : $batchId
            ]);

            $this->installedTime = $record->created_at->format('d-m-Y H:i:s');
            $this->isInstalled   = true;

        } catch (\Exception $e) {
            $this->installationError = "An error occurred while installing '{$this->handle}': " . $e->getMessage();
            return false;
        }

        return true;
    }

    /**
     * Attempts to uninstall the item. On failure, sets the $uninstallationError property with a descriptive message.
     *
     * @return bool
     */
    public function uninstall(): bool {
        $installedFeaturesCount = Migration::where('handle', '!=', $this->handle)->count();

        if ($installedFeaturesCount > 0) {
            $this->uninstallationError = "The core installable cannot be uninstalled while other features are still installed. Please uninstall all other features first.";
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
            $this->runner->down();

            Migration::where('handle', $this->handle)->delete();
        } catch (\Exception $e) {
            $this->uninstallationError = "An error occurred while uninstalling '{$this->handle}': " . $e->getMessage();
            return false;
        }

        return true;
    }

        /**
     * Checks if the installation or uninstallation process can be run in the current context.
     *
     * @return string|true Returns true if the process can be run, or a string error message if it cannot.
     */
    protected function canRunInstall(): string|true {
        // Check the context is right
        if (! is_admin() && ! app()->runningInConsole()) {
            return "The installation can only be performed in the admin area or via the command line.";
        }
        
        // Check user has permissions
        if (! current_user_can( 'manage_options' )) {
            return 'You do not have sufficient permissions to perform this installation.';
        }

        return true;
    }
}