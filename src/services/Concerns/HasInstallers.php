<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\Table;
use MM\Meros\Services\Registers\Tables as TableRegister;

use MM\Meros\App\Framework;
use MM\Meros\App\Models\Migration;

use MM\Meros\Facades\Framework as FrameworkAccessor;
use MM\Meros\Facades\Tables;

use Illuminate\Support\Facades\Log;

trait HasInstallers {
    private ?string $installedAt = null;
    private ?string $updatedAt = null;

    /**
     * Retrieves a table by its handle or the tables register.
     *
     * @param string       $handle
     * @param Closure|null $callback
     *
     * @return Table|TableRegister|null
     */
    protected function tables(string $handle = '', ?Closure $callback = null): Table|TableRegister|null {
        if (empty($handle)) {
            return Tables::checkout($this); // return register instance
        }

        else {
            return Tables::checkout($this)->get($handle, $callback);
        }
    }

    /**
     * Checks if the provider has any associated tables.
     *
     * @return bool
     */
    final public function hasTables(): bool {
        return $this->tables()->discover()->checkout($this)->all()->isNotEmpty();
    }

    /**
     * Installs the provider by installing all associated tables.
     *
     * @return void
     */
    final public function install(): void {
        $this->requireFramework(); // Ensure the framework is loaded before attempting to install tables
        $tables = $this->tables()->discover()->checkout($this)->all();

        $batchID = Str::ulid();

        foreach ($tables as $table) {
            if (!$table->isInstalled()) {
                $table->install($batchID);
            }
        }

        $this->installedAt = (new \DateTime())->format('Y-m-d H:i:s');
    }

    /**
     * Updates the provider by updating all associated tables that have updates available.
     *
     * @return void
     */
    final public function update(): void {
        $this->requireFramework(); // Ensure the framework is loaded before attempting to update tables
        $tables = $this->tables()->discover()->checkout($this)->all();

        $batchID = Str::ulid();

        foreach ($tables as $table) {
            $installed = $table->isInstalled();
            if ($installed && $table->hasUpdates()) {
                $table->update($batchID);
                $this->updatedAt = (new \DateTime())->format('Y-m-d H:i:s');
            }

            if (!$installed) {
                $table->install($batchID);
            }
        }
    }

    /**
     * Uninstalls the provider by uninstalling all associated tables.
     *
     * @return void
     */
    final public function uninstall(): void {
        $this->requireFramework(); // Ensure the framework is loaded before attempting to uninstall tables
        $tables = $this->tables()
            ->discover()
            ->checkout($this)
            ->all()
            ->reverse(); // Reverse the collection to uninstall in the correct order (dependents before dependencies)

        foreach ($tables as $table) {
            if ($table->isInstalled()) {
                $table->uninstall();
            }
        }

        $this->installedAt = null;
        $this->updatedAt = null;
    }

    final public function rollback(): void {
        $this->requireFramework(); // Ensure the framework is loaded before attempting to rollback tables
        
        $lastUpdate = Migration::where('provider', $this->getHandle())
            ->orderBy('created_at', 'desc')
            ->first();

        $table = $this->tables()
            ->discover()
            ->checkout($this)
            ->all()
            ->where('tableName', $lastUpdate->related_table)
            ->first();

        if ($table === null || !$table->isInstalled()) {
            return; // Table not found or not installed, cannot rollback
        }

        $table->rollback();
    }

    /**
     * Checks if the provider is installed by verifying if at least one associated table is installed.
     *
     * @return bool
     */
    final public function isInstalled(): bool {
        $tables = $this->tables()->discover()->checkout($this)->all();
    
        if ($tables->count() === 0) {
            return true; // If there are no tables, consider it installed
        }

        $installed = false;

        foreach ($tables as $table) {
            if ($table->isInstalled()) {
                $installed = true;
                $this->installedAt = $table->getInstalledAt();

                break; // Break the loop early if any table is installed
            }
        }

        return $installed;
    }

    /**
     * Checks if the provider has updates by verifying if any associated table is not installed or has updates available.
     *
     * @return bool
     */
    final public function hasUpdates(): bool {
        $tables = $this->tables()->discover()->checkout($this)->all();

        if ($tables->count() === 0) {
            return false; // If there are no tables, there can't be updates
        }
        
        $hasUpdates = false;

        foreach ($tables as $table) {
            if (!$table->isInstalled() || $table->hasUpdates()) {
                $hasUpdates = true;
                break; // Break the loop early if any table is not installed or has updates
            }
        }

        return $hasUpdates;
    }

    /**
     * Retrieves the installation timestamp of the provider.
     *
     * @return string|null
     */
    final public function installedAt(): ?string {
        return $this->installedAt;
    }

    /**
     * Retrieves the last update timestamp of the provider.
     *
     * @return string|null
     */
    final public function lastUpdated(): ?string {
        if (isset($this->updatedAt)) {
            return $this->updatedAt;
        }

        $this->updatedAt = Migration::where('provider', $this->getHandle())
            ->orderBy('created_at', 'desc')
            ->value('created_at');

        return $this->updatedAt;
    }

    /**
     * Calls the require method on the framework instance to ensure the required tables
     * for installations are in place.
     *
     * @return void
     */
    private function requireFramework(): void {
        if ($this instanceof Framework) {
            return;
        }

        FrameworkAccessor::require('migrations');
    }
}