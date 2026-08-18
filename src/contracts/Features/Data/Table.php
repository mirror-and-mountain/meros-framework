<?php

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Concerns\ResolvesPaths;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;

use MM\Meros\App\Models\Migration as MigrationModel;

use MM\Meros\Support\SchemaManager;
use MM\Meros\Facades\Tables;

final class Table extends Feature implements Makeable {
    /**
     * The table's name (identifier). 
     * It should correspond exactly to the table name in the database.
     *
     * @var string
     */
    private string $name = '';

    /**
     * The label from the table's main migration file.
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
     * @var TableCreator|null
     */
    private ?TableCreator $migration = null;

    /**
     * An array of update migrations associated with the table, keyed by their names.
     *
     * @var array
     */
    private array $updates = [];

    /**
     * An array of table dependents that rely on this table being installed first.
     *
     * @var array<Table>
     */
    private array $dependents = [];

    /**
     * An array of table dependencies that must be installed before this table can be installed.
     *
     * @var array
     */
    private array $dependencies = [];

    /**
     * Whether or not the table is required for the provider to function. Defaults to false.
     *
     * @var boolean
     */
    private bool $isRequired = false;

    /**
     * Whether to automatically install this table when its dependencies are installed. Defaults to true.
     *
     * @var boolean
     */
    private bool $installWithDependencies = true;

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

    use IsMakeable, ResolvesPaths;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        if (isset($this->passedProps['path'])) {
            $this->path($this->passedProps['path']);
        }
    }

    protected function whenConfigured(): void {
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
            throw new \RuntimeException("Migration path is not set for table '{$this->name}'.");
        }

        $migration = include $this->migrationPath;
        if (!$migration instanceof TableCreator) {
            throw new \RuntimeException("The migration file at '{$this->migrationPath}' does not return a valid TableCreator instance.");
        }

        if (!method_exists($migration, 'up') || !method_exists($migration, 'down')) {
            throw new \RuntimeException("The migration file at '{$this->migrationPath}' must implement both 'up' and 'down' methods.");
        }

        // Initialise the migration
        $migration->__init();

        // Set the table's name from the migration's name
        $this->name = $migration->getTableName();

        // Set the table's label from the migration's label
        $this->label = $migration->getLabel();
        
        // Set the description using the migration's description
        $this->description = $migration->getDescription();

        // Set whether the table is required for the provider to function
        $this->isRequired = $migration->isRequired();

        // Store any dependencies defined in the migration
        $this->dependencies = $migration->getDependencies();

        if (!empty($this->dependencies)) {
            if ($this->isRequired) {
                $this->installWithDependencies = true;
            } else {
                $this->installWithDependencies = $migration->installWithDependencies();
            }
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

                if ($updateMigration instanceof TableUpdater) {
                    if (!method_exists($updateMigration, 'up') || !method_exists($updateMigration, 'down')) {
                        continue; // Skip if the migration does not have the required methods
                    }

                    // Initialise the update migration
                    $updateMigration->__init();

                    // Get update's handle
                    $handle = $updateMigration->getHandle();
                    
                    // Get the update's label
                    $label = $updateMigration->getLabel();

                    // Get the description
                    $description = $updateMigration->getDescription();

                    $updateMigrations[$handle] = [
                        'migration'   => $updateMigration,
                        'table'       => $this->name,
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
    // Validation and Status Checks
    // =========================================================================

    /**
     * Checks if a specific migration operation can be run on the table, returning true if it can, or an error message if it cannot.
     *
     * @param string $operation
     * @param string $updateHandle
     *
     * @return true|string
     */
    public function canRunOperation(string $operation, string $updateHandle = ''): true|string {
        if (!in_array($operation, ['create', 'update', 'rollback', 'drop'])) {
            return "Invalid operation '{$operation}'.";
        }

        if ($this->migration === null) {
            return "No migration is set for the table '{$this->name}'.";
        }

        $schema = SchemaManager::schema($this->migration->getConnection());
        $context = [
            'operation'    => $operation,
            'handle'       => $updateHandle,
            'dependencies' => $this->dependencies,
        ];

        $result = SchemaManager::canRunOperation(
            $this->name,
            $schema,
            $context,
            false,
            true // Return error message instead of boolean
        );

        if ($result !== true) {
            return $result;
        }

        return true;
    }

    /**
     * Checks if the table can be installed in the database.
     *
     * @return boolean
     */
    public function canInstall(): bool {
        $canInstall = $this->canRunOperation('create');
        return $canInstall === true;
    }

    /**
     * Checks if the table is installed in the database, optionally checking for a specific update migration.
     *
     * @param string $updatename
     *
     * @return boolean
     */
    public function isInstalled(string $updatename = ''): bool {
        $tableInstalled = SchemaManager::tableIsInstalled($this);

        if ($updatename !== '') {
            $updateInstalled = SchemaManager::tableUpdateIsInstalled($this, $updatename);
            return $tableInstalled && $updateInstalled;
        }

        return $tableInstalled;
    }

    /**
     * Checks if there are any update migrations that have not yet been applied to the table.
     *
     * @return boolean
     */
    public function hasUpdates(): bool {
        if (empty($this->updates)) {
            return false;
        }

        $handles = collect($this->updates)->pluck('handle')->toArray();
        return SchemaManager::tableUpdatesAreInstalled($this, $handles) === false;
    }

    /**
     * Checks if the table has an update that can be rolled back.
     *
     * @return boolean
     */
    public function canRollback(): bool {
        $lastUpdate = $this->getLastUpdate();
        if ($lastUpdate === null) {
            return false;
        }

        $canRollback = $this->canRunOperation('rollback', $lastUpdate['handle']);
        return $canRollback === true;
    }

    // =========================================================================
    // Operations
    // =========================================================================

    /**
     * Attempts to run a migration operation (up or down) on the table, returning true on success or an error message on failure.
     *
     * @param Migration $migration
     * @param string    $operation
     *
     * @return true|string
     */
    private function runMigration(Migration $migration, string $operation): true|string {
        if (!method_exists($migration, $operation)) {
            throw new \RuntimeException("The migration does not have a method named '{$operation}'.");
        }

        try {
            $migration->{$operation}($this);
            return true;
        } catch (\Exception $e) {
            return "Migration operation '{$operation}' failed: " . $e->getMessage();
        }
    }

    /**
     * Installs the table in the database by running its main migration.
     *
     * @param string  $batchId
     *
     * @return static
     */
    public function install(string $batchId = ''): static {
        $canInstall = $this->canRunOperation('create');

        if ($canInstall !== true) {
            $this->lastError = $canInstall;
            return $this;
        }

        $this->currentBatchId = $batchId ?: Str::ulid();

        $result = $this->runMigration($this->migration, 'up');

        if ($result !== true) {
            $this->lastError = $result;
            return $this;
        }

        // Run any pending updates after the main migration is installed
        $this->update($batchId);

        if (!empty($this->dependents)) {
            $this->installDependents();
        }

        return $this;
    }

    /**
     * Installs any dependent tables that rely on this table being installed first and are 
     * set to auto-install with their dependencies. Will skip any dependents with other uninstalled dependencies.
     *
     * @return void
     */
    private function installDependents(): void {
        foreach ($this->dependents as $dependent) {
            if ($dependent->isInstalled()) {
                continue;
            }

            if (!$dependent->autoInstallsWithDependencies()) {
                continue;
            }

            $dependencies = $dependent->getDependencies();

            foreach ($dependencies as $dependency) {
                if ($dependency === $this->name) {
                    continue;
                }

                $table = Tables::get($dependency);

                if ($table instanceof Table && !$table->isInstalled()) {
                    continue 2; // Skip this dependent if any of its other dependencies are not installed
                }
            }

            $dependent->install($this->currentBatchId);
        }
    }

    /**
     * Updates the table by applying any pending update migrations.
     *
     * @param string $batchId
     *
     * @return static
     */
    public function update(string $batchId = ''): static {
        if (!$this->isInstalled()) {
            $this->lastError = static::class . " is not installed, so it cannot be updated.";
            return $this;
        }

        if (!$this->hasUpdates()) {
            return $this;
        }

        $updated = false;
        $this->currentBatchId = $batchId ?: Str::ulid();

        $this->walkUpdates(function($migration, $name) use (&$updated) {
            if (!$this->isInstalled($name)) {
                $canUpdate = $this->canRunOperation('update', $migration->getHandle());

                if ($canUpdate !== true) {
                    $this->lastError = $canUpdate;
                    return;
                }

                $result = $this->runMigration($migration, 'up');
                if ($result === true) {
                    $updated = true;
                } else {
                    $this->lastError = $result;
                    return;
                }
            }
        });

        return $this;
    }

    /**
     * Rolls back the last applied update migration for the table.
     *
     * @param string $batchId
     *
     * @return static
     */
    public function rollback(string $batchId = ''): static {
        $lastUpdate = $this->getLastUpdate();

        if ($lastUpdate === null) {
            return $this;
        }

        $canRollback = $this->canRunOperation('rollback', $lastUpdate['handle']);

        if ($canRollback !== true) {
            $this->lastError = $canRollback;
            return $this;
        }

        $this->currentBatchId = $batchId ?: Str::ulid();

        $migration = $lastUpdate['migration'];
        $result    = $this->runMigration($migration, 'down');

        if ($result !== true) {
            $this->lastError = $result;
        }

        return $this;
    }

    /**
     * Uninstalls the table from the database by rolling back its main migration.
     *
     * @return static
     */
    public function uninstall(): static {
        if ($this->name === 'meros_migrations') {
            return $this; // Prevent uninstallation of the core migrations table
        }

        if (!empty($this->dependents)) {
            foreach ($this->dependents as $dependent) {
                if ($dependent->isInstalled()) {
                    $this->lastError = "Cannot uninstall '{$this->name}' because dependent table '{$dependent->getName()}' is still installed.";
                    return $this;
                }
            }
        }

        $canUninstall = $this->canRunOperation('drop');

        if ($canUninstall !== true) {
            $this->lastError = $canUninstall;
            return $this;
        }

        $result = $this->runMigration($this->migration, 'down');

        if ($result !== true) {
            $this->lastError = $result;
        }

        return $this;
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

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    public function setIdentifier(string $identifier): static {
        return $this->name($identifier);
    }

    /**
     * Sets the table's name (identifier) in snake_case format.
     * 
     * Private as this class generally derives the name from the migration file name, 
     * but can be set explicitly if needed via the setIdentifier() method.
     *
     * @param string $name
     *
     * @return static
     */
    private function name(string $name): static {
        $this->name = Str::snake($name);
        return $this;
    }

    /**
     * Adds a dependent table to this table, indicating that the dependent table relies on this table being installed first.
     *
     * @param Table $dependent
     *
     * @return static
     */
    public function dependent(Table $dependent): static {
        $this->dependents[] = $dependent; 
        return $this;
    }

    /**
     * Sets the table's migration file path.
     *
     * @param string $path
     *
     * @return static
     */
    public function path(string $path): static {
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
    public function getInstalledAt(): ?string {
        return MigrationModel::where('related_table', $this->name)
            ->where('type', 'create')
            ->value('created_at')?->format('Y-m-d H:i:s');
    }

    /**
     * Returns the timestamp of the last update migration applied to the table, or null if no updates have been applied.
     *
     * @return string|null
     */
    public function getLastUpdatedAt(): ?string {
        return MigrationModel::where('related_table', $this->name)
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
    public function getInfo(): array {
        if (!$this->isInstalled()) {
            return [
                'provider'    => $this->getProvider()->getname(),
                'name'        => $this->getName(),
                'label'       => $this->getLabel(),
                'description' => $this->getDescription(),
                'installed'   => false,
            ];
        }

        $installedAt = $this->getInstalledAt();
        $lastUpdated = $this->getLastUpdatedAt();

        return [
            'provider'     => $this->getProvider()->getName(),
            'name'         => $this->getName(),
            'label'        => $this->getLabel(),
            'description'  => $this->getDescription(),
            'installed'    => true,
            'installed_at' => $installedAt,
            'last_updated' => $lastUpdated,
        ] + SchemaManager::getTableData($this->name);
    }

    /**
     * Returns the table's identifier (name/name).
     *
     * @return string
     */
    public function getIdentifier(): string {
        return $this->name;
    }

    /**
     * Returns the table's name (identifier/name).
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the table's label.
     *
     * @return string
     */
    public function getLabel(): string {
        return $this->label;
    }

    /**
     * Returns the table's description.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns whether the table is required for the provider to function.
     *
     * @return boolean
     */
    public function isRequired(): bool {
        return $this->isRequired;
    }

    /**
     * Returns the directory containing the table's migration file.
     *
     * @return string
     */
    public function getMigrationDirectory(): string {
        return $this->migrationDirectory;
    }

    /**
     * Returns the path to the table's migration file.
     *
     * @return string
     */
    public function getMigrationPath(): string {
        return $this->migrationPath;
    }

    /**
     * Returns the Migration instance associated with the table.
     *
     * @return Migration|null
     */
    public function getMigration(): ?Migration {
        return $this->migration;
    }

    /**
     * Returns the database connection name associated with the table's migration.
     *
     * @return string|null
     */
    public function getConnection(): string|null {
        if ($this->migration !== null) {
            return $this->migration->getConnection();
        }

        return null;
    }

    /**
     * Returns any update migrations associated with the table, keyed by their names.
     *
     * @return array
     */
    public function getUpdates(): array {
        return $this->updates;
    }

    /**
     * Returns the last update migration that was applied to the table, 
     * or null if no updates have been applied.
     *
     * @return array|null
     */
    public function getLastUpdate(): ?array {
        $reverseUpdates = array_reverse($this->updates);

        if (count($reverseUpdates) === 0) {
            return null;
        }

        foreach ($reverseUpdates as $update) {
            $installed = SchemaManager::tableUpdateIsInstalled($this, $update['handle']);
            if ($installed === true) {
                return $update;
            }
        }

        return null;
    }

    /**
     * Returns the table's dependencies that must be installed before this table can be installed.
     *
     * @return array
     */
    public function getDependencies(): array {
        return $this->dependencies;
    }

    /**
     * Returns whether this table should be automatically installed when its dependencies are installed.
     *
     * @return boolean
     */
    public function autoInstallsWithDependencies(): bool {
        return $this->installWithDependencies;
    }

    /**
     * Returns the current batch ID for the table's migrations.
     *
     * @return string
     */
    public function getBatchId(): string {
        return $this->currentBatchId;
    }

    /**
     * Returns the last error message encountered during a migration operation.
     *
     * @return string
     */
    public function getLastError(): string {
        return $this->lastError;
    }
}