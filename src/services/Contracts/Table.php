<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Support\Migration;
use MM\Meros\Support\SchemaManager;

use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\App\Models\Migration as MigrationModel;

final class Table extends FeatureDefinition {
    /**
     * The name of the table.
     *
     * @var string
     */
    public string $tableName = '';

    /**
     * The migration handle for the table.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * The migration label for the table.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The migration file for the table.
     *
     * @var string
     */
    protected string $path = '';

    /**
     * The current batch ID for the table's migrations. This is used to group related migrations together.
     *
     * @var string
     */
    protected string $currentBatchID = '';

    /**
     * An array of updates for the table, keyed by their migration handle. Each update contains the migration instance, handle, label, and path.
     *
     * @var array<string, array{migration: Migration, handle: string, label: string, path: string}>
     */
    protected array $updates = [];

    /**
     * Whether the table is ready to undertake operations.
     *
     * @var bool
     */
    protected bool $ready = false;

    /**
     * The last error message encountered during installation or rollback operations.
     *
     * @var string
     */
    protected string $lastError = '';

    /**
     * The migration instance for the table.
     *
     * @var Migration|null
     */
    protected ?Migration $migration = null;

    public function __construct(
        FeatureProvider $provider,
        string          $tableName,
        string          $migrationPath,
        array           $updates = [],
        string          $batchID = ''
    ) {
        $this->provider       = $provider;
        $this->tableName      = Str::snake($tableName);
        $this->currentBatchID = $batchID ?: Str::ulid();

        $migrationData = $this->instantiateMigration($migrationPath);

        $this->migration = $migrationData['migration'];
        $this->handle    = $migrationData['handle'];
        $this->label     = $migrationData['label'];
        $this->path      = $migrationData['path'];

        foreach ($updates as $update) {
            $updateData = $this->instantiateMigration($update);

            $this->updates[$updateData['handle']] = [
                'migration' => $updateData['migration'],
                'handle'    => $updateData['handle'],
                'label'     => $updateData['label'],
                'path'      => $updateData['path'],
            ];
        }

        $this->setReady();
    }

    /**
     * Sets the table's ready status.
     *
     * @return void
     */
    protected function setReady(): void {
        $requiredProps = [
            'tableName',
            'handle',
            'label',
            'path',
            'migration',
        ];

        foreach ($requiredProps as $prop) {
            if (empty($this->$prop) || is_null($this->$prop)) {
                $this->ready = false;
                return;
            }
        }

        $this->ready = true;
    }

    /***************************
     * Contract methods
     ***************************/

    protected function queue(): void {
        // Not used
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Install the table by running its migration.
     * 
     * @param string|null $batchID Optional batch ID to associate with the migration. If not provided, a new ULID will be generated.
     * @param bool        $update Whether to apply any available updates after installing the main migration. Defaults to true.
     *
     * @return self
     */
    public function install(?string $batchID = null, bool $update = true): self {
        $canInstall = $this->canInstall();

        if ($canInstall !== true) {
            $this->lastError = $canInstall;
            return $this;
        }

        if (!method_exists($this->migration, 'up')) {
            $this->lastError = "Migration instance does not have an 'up' method: " . get_class($this->migration);
            return $this;
        }

        $this->currentBatchID = $batchID ?: Str::ulid();

        $this->migration->up($this->handle);

        if ($update) {
            $this->update($batchID);
        }

        return $this;
    }

    /**
     * Apply any available updates for the table by running their migrations.
     * 
     * @param string|null $batchID Optional batch ID to associate with the updates. If not provided, a new ULID will be generated.
     *
     * @return self
     */
    public function update(?string $batchID = null): self {
        if (!$this->isInstalled()) {
            $this->lastError = static::class . " is not installed, cannot apply updates.";
            return $this;
        }

        if (!$this->ready) {
            $this->lastError = static::class . " is not ready to be updated. Please ensure all required properties are set and valid.";
            return $this;
        }

        $updated = false;

        $this->currentBatchID = $batchID ?: Str::ulid();

        $this->walkUpdates(function($migration, $handle) use (&$updated) {
            if ($this->isInstalled($handle)) {
                return; // Skip updates that have already been applied
            }

            if (!method_exists($migration, 'up')) {
                $this->lastError = "Migration instance does not have an 'up' method: " . get_class($migration);
                return;
            }

            $migration->up($handle);
            $updated = true;
        });

        if ($updated === false) {
            $this->lastError = "No updates were applied. Either there are no updates, or all updates have already been applied.";
        }

        return $this;
    }

    /**
     * Rolls back the last applied update for the table, or if there are no updates, rolls back the main table migration.
     *
     * @return self
     */
    public function rollback(): self {
        $canRollback = $this->canRollback();

        if ($canRollback !== true) {
            $this->lastError = $canRollback;
            return $this;
        }

        $reverseUpdates = array_reverse($this->updates);

        // Rollback the last installed update if available...
        if (count($reverseUpdates) > 0) {
            foreach ($reverseUpdates as $update) {
                $installed = $this->isInstalled($update['handle']);

                if (!$installed) {
                    continue; // Skip updates that have not been applied
                }

                if (!method_exists($update['migration'], 'down')) {
                    $this->lastError = "Migration instance does not have a 'down' method: " . get_class($update['migration']);
                    return $this;
                }

                $update['migration']->down($update['handle']);

                break; // Only roll back the most recent update
            }
        }

        // ...if there are no updates to roll back, roll back the main migration
        else {
            $this->uninstall();
        }

        return $this;
    }

    /**
     * Uninstall the table by running the main migration's down method.
     *
     * @return self 
     */
    public function uninstall(): self {
        if ($this->tableName === 'meros_migrations') {
            return $this; // Prevent uninstalling the migrations table through this method to avoid potential issues with tracking migrations. Uninstalling this table should be done manually with caution if needed.
        }

        $canUninstall = $this->canRollback();

        if ($canUninstall !== true) {
            $this->lastError = $canUninstall;
            return $this;
        }

        if (!method_exists($this->migration, 'down')) {
            $this->lastError = "Migration instance does not have a 'down' method: " . get_class($this->migration);
            return $this;
        }

        $this->migration->down($this->handle);
        return $this;
    }

    /**
     * Check if the given table or update is installed.
     *
     * @return bool True if the table is installed, false otherwise.
     */
    public function isInstalled(string $updateHandle = ''): bool {
        $serviceInstalled = SchemaManager::hasTable('meros_migrations');

        if (!$serviceInstalled) {
            return false;
        }

        $tableExists = SchemaManager::hasTable($this->tableName);

        if (!empty($updateHandle)) {
            $updateLogged = MigrationModel::where('related_table', $this->tableName)->where('handle', $updateHandle)->exists();
            return $tableExists && $updateLogged;
        }

        $tableLogged = MigrationModel::where('related_table', $this->tableName)->where('type', 'create')->exists();

        return $tableExists && $tableLogged;
    }

    /**
     * Check if the table has updates that have not been applied.
     *
     * @return bool True if there are unapplied updates, false otherwise.
     */
    public function hasUpdates(): bool {
        $hasUpdates = false;

        foreach ($this->updates as $update) {
            $installed = $this->isInstalled($update['handle']);

            if (!$installed) {
                $hasUpdates = true;
                break;
            }
        }

        return $hasUpdates;
    }

    /**
     * Get the table's information, columns, indexes, and foreign keys if it is installed.
     * Limited information is returned if the table is not installed.
     *
     * @return array|null An associative array containing table information if available.
     */
    public function getInfo(): array {
        if (!$this->isInstalled()) {
            return [
                'provider'  => $this->provider->getHandle(),
                'name'      => $this->tableName,
                'installed' => false,
            ];
        }

        $installedAt = $this->getInstalledAt();
        $lastUpdated = $this->getLastUpdated();

        return [
            'provider'     => $this->provider->getHandle(),
            'name'         => $this->tableName,
            'installed'    => true,
            'installed_at' => $installedAt,
            'last_updated' => $lastUpdated,
        ] + SchemaManager::getTableData($this->tableName);
    }

    /**
     * Get the timestamp of when the table was installed.
     *
     * @return string|null The installation timestamp, or null if not available.
     */
    public function getInstalledAt(): ?string {
        return MigrationModel::where('related_table', $this->tableName)
            ->where('type', 'create')
            ->value('created_at');
    }

    /**
     * Get the timestamp of when the table was last updated.
     *
     * @return string|null The last update timestamp, or null if not available.
     */
    public function getLastUpdated(): ?string {
        return MigrationModel::where('related_table', $this->tableName)
            ->where('type', 'update')
            ->orderBy('created_at', 'desc')
            ->value('created_at');
    }

    /**
     * Get the last error message encountered during installation or rollback operations.
     *
     * @return string The last error message.
     */
    public function getLastError(): string {
        return $this->lastError;
    }

    /***************************
     * Getters
     ***************************/

    public function getTableName(): string {
        return $this->tableName;
    }

    public function getHandle(): string {
        return $this->handle;
    }

    public function getLabel(): string {
        return $this->label;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getBatchID(): string {
        return $this->currentBatchID;
    }

    public function getUpdates(): array {
        return $this->updates;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Instantiates a migration from the given path and returns extracted metadata.
     *
     * @param string $path
     *
     * @return array An associative array containing the migration instance and its metadata.
     * @throws \InvalidArgumentException If the migration file does not return a valid Migration instance or has an invalid name format.
     */
    protected function instantiateMigration(string $path): array {
        if (!File::exists($path) || !File::isFile($path)) {
            throw new \InvalidArgumentException("Migration file not found: $path");
        }

        $migration = include $path;

        if (!$migration instanceof Migration) {
            throw new \InvalidArgumentException("Migration file does not return a valid Migration instance: $path");
        }

        // Creates a handle from the migration file name
        $handle = Str::beforeLast(basename($path), '.');

        // Remove timestamp and numeric prefixes
        $handleWithoutTimeStamp = preg_replace('/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', '', $handle);

        return [
            'migration' => $migration,
            'handle'    => $handle,
            'label'     => Str::title(Str::replace('_', ' ', $handleWithoutTimeStamp)),
            'path'      => $path,
        ];
    }

    /**
     * Checks if the table can be installed. Returns true if the table can be installed, or a string error message if it cannot.
     *
     * @return string|true
     */
    protected function canInstall(): string|true {
        if (!is_admin() && !app()->runningInConsole()) {
            return static::class . " can only be installed in the admin area or via WP-CLI.";
        }

        if (!current_user_can('manage_options')) {
            return "Current user does not have permission to install " . static::class . ".";
        }

        if ($this->isInstalled()) {
            return static::class . " is already installed.";
        }

        if (!$this->ready) {
            return static::class . " is not ready to be installed. Please ensure all required properties are set and valid.";
        }

        return true;
    }

    /**
     * Checks if the table can be rolled back. Returns true if the table can be rolled back, or a string error message if it cannot.
     *
     * @return string|true
     */ 
    protected function canRollback(): string|true {
        if (!is_admin() && !app()->runningInConsole()) {
            return static::class . " can only be rolled back in the admin area or via WP-CLI.";
        }

        if (!current_user_can('manage_options')) {
            return "Current user does not have permission to rollback " . static::class . ".";
        }

        if (!$this->isInstalled()) {
            return static::class . " is not installed, cannot rollback.";
        }

        if (!$this->ready) {
            return static::class . " is not ready to be rolled back. Please ensure all required properties are set and valid.";
        }

        return true;
    }

    /**
     * Walks through the table's updates and applies the given callback to each, passing the migration instance and handle as arguments.
     *
     * @param Closure $callback
     *
     * @return void
     */
    protected function walkUpdates(Closure $callback): void {
        foreach ($this->updates as $update) {
            $migration = $update['migration'];
            $handle    = $update['handle'];

            $callback($migration, $handle);
        }
    }
}