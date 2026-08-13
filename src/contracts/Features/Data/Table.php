<?php

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\Feature;

use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Concerns\ResolvesPaths;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;

use MM\Meros\App\Models\Migration as MigrationModel;

use MM\Meros\Support\SchemaManager;

class Table extends Feature implements Registrable, Makeable {
    /**
     * The table's handle (identifier). 
     * It should correspond exactly to the table name in the database.
     *
     * @var string
     */
    private string $handle = '';

    /**
     * The table's label.
     *
     * @var string
     */
    private string $label = '';

    /**
     * The table's description.
     *
     * @var string
     */
    private string $description = '';

    /**
     * The directory containing the table's migration file(s).
     *
     * @var string
     */
    private string $migrationDirectory = '';

    /**
     * The path to the table's main migration file (i.e. the migration that creates the table).
     *
     * @var string
     */
    private string $migrationPath = '';

    /**
     * The main Migration instance associated with the table (i.e. the migration that creates the table).
     *
     * @var Migration|null
     */
    private ?Migration $migration = null;

    /**
     * An array of update migrations associated with the table, keyed by their handles.
     *
     * @var array
     */
    private array $updates = [];

    /**
     * The current batch ID for the table's migrations.
     *
     * @var string
     */
    private string $currentBatchId = '';

    /**
     * The last error message encountered during a migration operation.
     *
     * @var string
     */
    private string $lastError = '';

    use IsRegistrable, IsMakeable, ResolvesPaths;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        if (isset($this->passedProps['path'])) {
            $this->path($this->passedProps['path']);
        }
    }

    final protected function whenConfigured(): void {
        if ($this->migrationDirectory === '' || $this->migrationPath === '') {
            return;
        }

        $this->initialiseFromMigrationPath();
    }

    // =========================================================================
    // Migration Management
    // =========================================================================

    /**
     * Initialises the table's properties based on the main migration file 
     * located at $this->migrationPath.
     *
     * @return void
     */
    private function initialiseFromMigrationPath(): void {
        if ($this->migrationPath === '' || $this->migrationDirectory === '') {
            throw new \RuntimeException("Migration path is not set for table '{$this->handle}'.");
        }

        $migration = include $this->migrationPath;
        if (!$migration instanceof Migration) {
            throw new \RuntimeException("The migration file at '{$this->migrationPath}' does not return a valid Migration instance.");
        }

        if (!method_exists($migration, 'up') || !method_exists($migration, 'down')) {
            throw new \RuntimeException("The migration file at '{$this->migrationPath}' must implement both 'up' and 'down' methods.");
        }

        // Create a handle from the migration file name
        $handle = Str::snake(basename($this->migrationDirectory));

        // Remove timestamp and numeric prefixes from the handle
        $this->handle = preg_replace('/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', '', $handle);

        // Set the label using the handle if not already set
        if ($this->label === '') {
            $this->label = Str::title(str_replace('_', ' ', $this->handle));
        }

        // Set the description using the migration's description if not already set
        if ($this->description === '') {
            $this->description = $migration->description ?? '';
        }

        $this->migration = $migration;
        $this->initialiseUpdateMigrations();
    }

    /**
     * Initialises the update migrations for the table by scanning the 'updates' 
     * directory inside the main migration directory.
     *
     * @return void
     */
    private function initialiseUpdateMigrations(): void {
        if ($this->migrationPath === '' || 
            $this->migrationDirectory === '' || 
            $this->migration === null
        ) {
            return;
        }

        $updatesDirectory = trailingslashit($this->migrationDirectory) . 'updates';

        if (!$this->pathIsDirectory($updatesDirectory)) {
            return;
        }

        $updateCandidates = File::files($updatesDirectory);
        
        if (empty($updateCandidates)) {
            return;
        }

        $updateMigrations = [];
        foreach ($updateCandidates as $candidate) {
            if ($this->fileHasExtensions($candidate->getPathname(), ['php'], false)) {
                $path = $candidate->getPathname();
                $updateMigration = include $path;

                if ($updateMigration instanceof Migration) {
                    if (!method_exists($updateMigration, 'up') || !method_exists($updateMigration, 'down')) {
                        continue; // Skip if the migration does not have the required methods
                    }

                    // Create a handle from the migration file name, 
                    // removing timestamp or any numeric prefixes
                    $handle = Str::snake(
                        preg_replace(
                            '/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', 
                            '', 
                            $candidate->getFilenameWithoutExtension()
                        )
                    );
                    
                    // Create a label from the handle.
                    $label = Str::title(str_replace('_', ' ', $handle));

                    // Get the description if available, otherwise use an empty string.
                    $description = $updateMigration->description ?? '';

                    $updateMigrations[$handle] = [
                        'migration'   => $updateMigration,
                        'handle'      => $handle,
                        'label'       => $label,
                        'description' => $description,
                        'path'        => $path,
                    ];
                }
            }
        }

        $this->updates = $updateMigrations;
    }

    // =========================================================================
    // Installation, Updating, Rollback and Uninstallation
    // =========================================================================

    /**
     * Installs the table in the database by running its main migration.
     *
     * @param string  $batchId
     * @param boolean $update
     *
     * @return static
     */
    final public function install(string $batchId = '', bool $update = true): static {
        $canInstall = $this->canInstall();

        if ($canInstall !== true) {
            $this->lastError = $canInstall;
            return $this;
        }

        $this->currentBatchId = $batchId ?: Str::ulid();

        $this->migration->up($this->handle);

        if ($update) {
            $this->update($batchId);
        }

        return $this;
    }

    /**
     * Updates the table by applying any pending update migrations.
     *
     * @param string $batchId
     *
     * @return static
     */
    final public function update(string $batchId = ''): static {
        if (!$this->isInstalled()) {
            $this->lastError = static::class . " is not installed, so it cannot be updated.";
            return $this;
        }

        if (!$this->hasUpdates()) {
            $this->lastError = static::class . " has no updates to apply.";
            return $this;
        }

        $updated = false;
        $this->currentBatchId = $batchId ?: Str::ulid();

        $this->walkUpdates(function($migration, $handle) use (&$updated) {
            if (!$this->isInstalled($handle)) {
                $canUpdate = $this->canUpdate($handle);

                if ($canUpdate !== true) {
                    $this->lastError = $canUpdate;
                    return;
                }

                $migration->up($this->handle);
                $updated = true;
            }
        });

        if ($updated === false) {
            $this->lastError = "No updates were applied. Either there are no updates, or all updates have already been applied.";
        }

        return $this;
    }

    final public function rollback(): static {
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
                    continue;
                }

                $update['migration']->down($this->handle);
                break; // Rollback only the most recent update
            }
        }

        else {
            // ...If there are no updates, rollback the main migration
            $this->migration->down($this->handle);
        }

        return $this;
    }

    /**
     * Uninstalls the table from the database by rolling back its main migration.
     *
     * @return static
     */
    final public function uninstall(): static {
        if ($this->handle === 'meros_migrations') {
            return $this; // Prevent uninstallation of the core migrations table
        }

        $canUninstall = $this->canRollback();

        if ($canUninstall !== true) {
            $this->lastError = $canUninstall;
            return $this;
        }

        $this->migration->down($this->handle);
        return $this;
    }



    /**
     * Checks if the table is installed in the database, optionally checking for a specific update migration.
     *
     * @param string $updateHandle
     *
     * @return boolean
     */
    final public function isInstalled(string $updateHandle = ''): bool {
        $serviceInstalled = SchemaManager::hasTable('meros_migrations');

        if (!$serviceInstalled) {
            return false;
        }

        $tableExists = SchemaManager::hasTable($this->handle);

        if (!empty($updateHandle)) {
            $updateLogged = MigrationModel::where('related_table', $this->handle)
                ->where('handle', $updateHandle)
                ->exists();

            return $tableExists && $updateLogged;
        }

        $tableLogged = MigrationModel::where('related_table', $this->handle)
            ->where('type', 'create')
            ->exists();

        return $tableExists && $tableLogged;
    }

    /**
     * Checks if there are any update migrations that have not yet been applied to the table.
     *
     * @return boolean
     */
    final public function hasUpdates(): bool {
        if (empty($this->updates)) {
            return false;
        }

        $hasUpdates = false;

        foreach ($this->updates as $update) {
            if (!$this->isInstalled($update['handle'])) {
                $hasUpdates = true;
                break;
            }
        }

        return $hasUpdates;
    }

    /**
     * Checks whether the table can be installed, returning true if it can, or a string message explaining why it cannot.
     *
     * @return string|true
     */
    private function canInstall(): string|true {
        $configured = $this->isConfigured();
        if ($configured !== true) {
            return $configured;
        }

        $allowedContext = $this->isAllowedContext();
        if ($allowedContext !== true) {
            return $allowedContext;
        }

        $userPermission = $this->userHasPermission();
        if ($userPermission !== true) {
            return $userPermission;
        }

        if ($this->isInstalled()) {
            return static::class . " is already installed.";
        }

        return true;
    }

    /**
     * Checks whether a specific update migration can be applied to the table, returning true if it can, or a string message explaining why it cannot.
     *
     * @param string $updateHandle
     *
     * @return string|true
     */
    private function canUpdate(string $updateHandle): string|true {
        $configured = $this->isConfigured();
        if ($configured !== true) {
            return $configured;
        }

        $allowedContext = $this->isAllowedContext();
        if ($allowedContext !== true) {
            return $allowedContext;
        }

        $userPermission = $this->userHasPermission();
        if ($userPermission !== true) {
            return $userPermission;
        }

        if (!$this->isInstalled()) {
            return static::class . " is not installed, so it cannot be updated.";
        }

        if (!array_key_exists($updateHandle, $this->updates)) {
            return "Update migration '{$updateHandle}' does not exist for " . static::class . ".";
        }

        if ($this->isInstalled($updateHandle)) {
            return "Update migration '{$updateHandle}' has already been applied to " . static::class . ".";
        }

        return true;
    }

    /**
     * Checks whether the table can be rolled back, returning true if it can, or a string message explaining why it cannot.
     *
     * @return string|true
     */
    private function canRollback(): string|true {
        $configured = $this->isConfigured();
        if ($configured !== true) {
            return $configured;
        }

        $allowedContext = $this->isAllowedContext();
        if ($allowedContext !== true) {
            return $allowedContext;
        }

        $userPermission = $this->userHasPermission();
        if ($userPermission !== true) {
            return $userPermission;
        }

        if (!$this->isInstalled()) {
            return static::class . " is not installed, so it cannot be rolled back.";
        }

        return true;
    }

    /**
     * Walks through each update migration and applies a given callback function to it.
     *
     * @param Closure $callback
     *
     * @return void
     */
    private function walkUpdates(Closure $callback): void {
        foreach ($this->updates as $update) {
            $migration = $update['migration'];
            $handle    = $update['handle'];

            $callback($migration, $handle);
        }
    }

    /**
     * Checks that the table's migration is properly configured, returning true if it is, or a string message explaining why it is not.
     *
     * @return string|true
     */
    private function isConfigured(): string|true {
        if ($this->migrationPath === '' || $this->migrationDirectory === '' || $this->migration === null) {
            return "The migration for " . static::class . " is not properly configured. Please ensure the migration path and directory are set correctly.";
        }

        return true;
    }

    /**
     * Checks whether the current context allows for table operations, returning true if it does, or a string message explaining why it does not.
     *
     * @return string|true
     */
    private function isAllowedContext(): string|true {
        if (!is_admin() && !app()->runningInConsole()) {
            return static::class . " can only be used in the admin area or via WP-CLI.";
        }

        return true;
    }

    /**
     * Checks whether the current user has permission to manage tables, returning true if they do, or a string message explaining why they do not.
     *
     * @return string|true
     */
    private function userHasPermission(): string|true {
        if (!current_user_can('manage_options')) {
            return "Current user does not have permission to manage " . static::class . ".";
        }

        return true;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $identifier): static {
        return $this->handle($identifier);
    }

    /**
     * Sets the table's handle (identifier) in snake_case format.
     * 
     * Private as this class generally derives the handle from the migration file name, 
     * but can be set explicitly if needed via the setIdentifier() method.
     *
     * @param string $handle
     *
     * @return static
     */
    private function handle(string $handle): static {
        $this->handle = Str::snake($handle);
        return $this;
    }

    /**
     * Sets the table's migration file path.
     *
     * @param string $path
     *
     * @return static
     */
    final public function path(string $path): static {
        $this->migrationPath = $this->resolveMigrationPath($path);
        return $this;
    }

    /**
     * Resolves the migration file path, checking both the provided path and a potential path relative to the provider's base path.
     *
     * @param string $path
     *
     * @return string
     * @throws \InvalidArgumentException if the resolved path does not point to a valid migration file.
     */
    private function resolveMigrationPath(string $path): string {
        if ($this->pathLooksAbsolute($path)) {
            return $this->resolveAbsoluteMigrationPath($path);
        } else {
            return $this->resolveRelativeMigrationPath($path);
        }
    }

    /**
     * Resolves an absolute migration file path, checking if it points to a valid migration file or directory.
     *
     * @param string $path
     *
     * @return string
     * @throws \InvalidArgumentException if the resolved path does not point to a valid migration file or directory.
     */
    private function resolveAbsoluteMigrationPath(string $path): string {
        if ($this->pathIsFile($path)) {
            // Validate the file extension (throws error on failure)
            $this->fileHasExtensions($path, ['php'], true);
            $this->migrationDirectory = dirname($path);
            return $path;
        }

        else if ($this->pathIsDirectory($path)) {
            $migrationCandidate = $this->getFirstFileInDirectoryWithExtensions($path, ['php']);
            
            if ($migrationCandidate !== null) {
                $this->migrationDirectory = $path;
                return $migrationCandidate;
            }

            else {
                throw new \InvalidArgumentException("The provided directory '{$path}' does not contain any valid migration files.");
            }
        }

        else {
            throw new \InvalidArgumentException("The provided path '{$path}' does not point to a valid migration file or directory.");
        }
    }

    /**
     * Resolves a relative migration file path, checking if it points to a valid migration file or directory.
     *
     * @param string $path
     *
     * @return string
     * @throws \InvalidArgumentException if the resolved path does not point to a valid migration file or directory.
     */
    private function resolveRelativeMigrationPath(string $path): string {
        $provider = $this->getProvider();
        $providerMigrationsPath = $provider->getPreference('tables_path');
        $path = rtrim($providerMigrationsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

        if ($this->pathIsFile($path)) {
            // Validate the file extension (throws error on failure)
            $this->fileHasExtensions($path, ['php'], true);

            $this->migrationDirectory = dirname($path);
            return $path;
        }

        else if ($this->pathIsDirectory($path)) {
            $migrationCandidate = $this->getFirstFileInDirectoryWithExtensions($path, ['php']);
            
            if ($migrationCandidate !== null) {
                $this->migrationDirectory = $path;
                return $migrationCandidate;
            }

            else {
                throw new \InvalidArgumentException("The provided directory '{$path}' does not contain any valid migration files.");
            }
        }

        else {
            throw new \InvalidArgumentException("The provided path '{$path}' does not point to a valid migration file or directory.");
        }
}

    // =========================================================================
    // Attribute Getters
    // =========================================================================

    /**
     * Returns the timestamp of when the table was first installed, or null if it has not been installed.
     *
     * @return string|null
     */
    final public function getInstalledAt(): ?string {
        return MigrationModel::where('related_table', $this->handle)
            ->where('type', 'create')
            ->value('created_at')?->format('Y-m-d H:i:s');
    }

    /**
     * Returns the timestamp of the last update migration applied to the table, or null if no updates have been applied.
     *
     * @return string|null
     */
    final public function getLastUpdated(): ?string {
        return MigrationModel::where('related_table', $this->handle)
            ->where('type', 'update')
            ->orderByDesc('created_at')
            ->value('created_at')?->format('Y-m-d H:i:s');
    }

    /**
     * Returns an array of information about the table, 
     * including its provider, name, label, description, installation status, and timestamps.
     *
     * @return array
     */
    final public function getInfo(): array {
        if (!$this->isInstalled()) {
            return [
                'provider'    => $this->getProvider()->getHandle(),
                'name'        => $this->getHandle(),
                'label'       => $this->getLabel(),
                'description' => $this->getDescription(),
                'installed'   => false,
            ];
        }

        $installedAt = $this->getInstalledAt();
        $lastUpdated = $this->getLastUpdated();

        return [
            'provider'     => $this->getProvider()->getHandle(),
            'name'         => $this->getHandle(),
            'label'        => $this->getLabel(),
            'description'  => $this->getDescription(),
            'installed'    => true,
            'installed_at' => $installedAt,
            'last_updated' => $lastUpdated,
        ] + SchemaManager::getTableData($this->handle);
    }

    /**
     * Returns the table's identifier (handle/name).
     *
     * @return string
     */
    final public function getIdentifier(): string {
        return $this->handle;
    }

    /**
     * Returns the table's handle (identifier/name).
     *
     * @return string
     */
    final public function getHandle(): string {
        return $this->handle;
    }

    /**
     * Returns the table's label.
     *
     * @return string
     */
    final public function getLabel(): string {
        return $this->label;
    }

    /**
     * Returns the table's description.
     *
     * @return string
     */
    final public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns the directory containing the table's migration file.
     *
     * @return string
     */
    final public function getMigrationDirectory(): string {
        return $this->migrationDirectory;
    }

    /**
     * Returns the path to the table's migration file.
     *
     * @return string
     */
    final public function getMigrationPath(): string {
        return $this->migrationPath;
    }

    /**
     * Returns the Migration instance associated with the table.
     *
     * @return Migration|null
     */
    final public function getMigration(): ?Migration {
        return $this->migration;
    }

    /**
     * Returns any update migrations associated with the table, keyed by their handles.
     *
     * @return array
     */
    final public function getUpdates(): array {
        return $this->updates;
    }

    /**
     * Returns the current batch ID for the table's migrations.
     *
     * @return string
     */
    final public function getBatchId(): string {
        return $this->currentBatchId;
    }
}