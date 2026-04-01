<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Models\Migration;
use MM\Meros\App\Features\Installable;
use MM\Meros\App\Facades\Framework;

use MM\Meros\App\Models\Integration;

trait HasInstallables {
    /**
     * Whether this item should automatically discover installables.
     *
     * @var bool
     */
    protected bool $discoverInstallables = false;

    /**
     * Whether this item has any installables registered.
     *
     * @var bool
     */
    protected bool $hasInstallables = false;
    
    /**
     * Discovers installables
     *
     * @return void
     */
    protected function discoverInstallables(): void {
        if (! $this->discoverInstallables) {
            return;
        }

        // Extend this to run only on specific admin pages...
        if (!is_admin() && !app()->runningInConsole()) {
            return;
        }
        
        $migrationsPath  = $this->path . $this->getPreference('migrations_path');
        $migrationsExist = File::exists($migrationsPath) && File::isDirectory($migrationsPath);
        $migrations      = $migrationsExist 
            ? collect(File::files($migrationsPath))->filter(fn($file) => $file->getExtension() === 'php')->toArray()
            : [];


        // Register installables for each migration file.
        foreach ($migrations as $migration) {
            $this->makeInstallable([
                'path' => $migration->getPathname(),
            ]);
        }

        if (count($migrations) > 0) {
            $this->hasInstallables = true;
        }
    }

    /**
     * Creates an installable instance from the given config and registers it.
     *
     * @param  array $config The installable configuration array.
     * 
     * @return Installable The created installable instance.
     */
    protected function makeInstallable(array $config): Installable {
        return app(
            Installable::class, [
                'source' => $this,
            ]
        )->make($config);
    }

    /**
     * Checks if the item is installed by checking that at least one installable has been run.
     * May be overridden to provide a different definition of "installed" if necessary.
     * 
     * @return bool
     */
    protected function isInstalled(): bool {
        $installedItems = $this->getInstallables()->where('isInstalled', true)->count();
        return $installedItems > 0;
    }

    /**
      * Checks if there are any pending installables that have not been run. 
      * May be overridden to provide a different definition of "has updates" if necessary.
      * 
      * @return bool
      */
    protected function hasUpdates(): bool {
        $notInstalledItems = $this->getInstallables()->where('isInstalled', false)->count();
        
        return $this->isInstalled() && $notInstalledItems > 0;
    }

    /**
     * Gets the item's migrations directory.
     *
     * @param bool $full Whether to return the full path or just the directory.
     * @return string
     */
    final public function getMigrationsDir(bool $full = true): string {
        return $full 
            ? $this->path . $this->getPreference('migrations_path')
            : $this->getPreference('migrations_path');
    }

    /**
     * Returns an array of installable objects registered by the item.
     * 
     * @param  bool $readyOnly Whether to return only installables that are ready.
     *
     * @return Collection
     */
    final public function getInstallables(bool $readyOnly = false): Collection {
        if ($readyOnly) {
            return $this->registry->get('installables')
                    ->where('source', $this)
                    ->where('ready', true) ?? collect([]);
        } else {
            return $this->registry->get('installables')
                    ->where('source', $this) ?? collect([]);
        }
    }

    /**
     * Installs all the ready installables registered by the item.
     *
     * @return bool|string Returns true on successful installation, or an error message on failure.
     */
    final public function install(): bool|string {
        // Ensure required services are installed before attempting to run installables
        $servicesInstalled = $this->installRequiredServices();
        if ($servicesInstalled !== true) {
            return $servicesInstalled; // Return the error message if service installation fails.
        }

        // Get ready installables
        $installables = $this->getInstallables(true);

        if ($installables->isEmpty()) {
            return true; // No installables to run, consider it a successful installation.
        }

        $batchId = Str::ulid();

        $installables->each(function($installable) use ($batchId) {
            $result = $installable->install($batchId);
            if ($result !== true) {
                return $installable->installationError; // Return the error message if installation fails.
            }
        });

        return true;
    }

    /**
     * Attempts to install each service required by the item.
     *
     * @return boolean|string
     */
    private function installRequiredServices(): bool|string {
        foreach ($this->requiredServices as $service) {
            if (!Framework::isServiceInstalled($service, true)) {
                return "Failed to install required service: $service";
            }
        }

        return true;
    }

    /**
     * Uninstalls all the ready installables registered by the item in reverse order.
     *
     * @return bool|string Returns true on successful uninstallation, or an error message on failure.
     */
    final public function uninstall(): bool|string {
        // Get ready installables in reverse order for uninstallation
        $installables = $this->getInstallables(true)->reverse();

        if ($installables->isEmpty()) {
            return true; // No installables to run, consider it a successful uninstallation.
        }

        $installables->each(function($installable) {
            $result = $installable->uninstall();
            if ($result !== true) {
                return $installable->uninstallationError; // Return the error message if uninstallation fails.
            }
    });

        return true;
    }

    /**
     * Returns a specific installable object registered by the item.
     *
     * @param  string $handle The handle of the installable to return.
     * 
     * @return Installable|null
     */
    final public function getInstallable(string $handle): Installable|null {
        $installable = collect($this->getInstallables())->firstWhere('handle', $handle);

        return $installable ?: null;
    }

    /**
     * Return the time the item was installed.
     *
     * @return ?string
     */
    final public function getInstalledTime(): ?string {
        $record = Migration::where('source', $this->handle)->orderBy('batch_id')->first();
        if ($record) {
            return $record->created_at->format('d-m-Y H:i:s');
        }

        return null;
    }

    /**
     * Return the time the item was last updated.
     *
     * @return ?string
     */
    final public function getUpdatedTime(): ?string {
        $record = Migration::where('source', $this->handle)->orderByDesc('batch_id')->first();
        if ($record) {
            return $record->updated_at->format('d-m-Y H:i:s');
        }

        return null;
    }

    /**
     * Returns whether the given tables are installed.
     *
     * @param  array   $tables
     *
     * @return boolean
     */
    protected function hasTables(array $tables): bool {
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Migration::where('related_table', $table)->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether the given table is installed.
     *
     * @param  string  $table
     *
     * @return boolean
     */
    protected function hasTable(string $table): bool {
        return Schema::hasTable($table) && Migration::where('related_table', $table)->exists();
    }
}