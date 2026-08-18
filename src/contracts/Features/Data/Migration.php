<?php 

namespace MM\Meros\Contracts\Features\Data;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration {
    /**
     * The migration's handle including the timestamp prefix.
     *
     * @var string
     */
    private string $fullHandle = '';

    /**
     * The migration's handle without the timestamp prefix.
     *
     * @var string
     */
    private string $handle = '';

    /**
     * The name of the table being migrated.
     *
     * @var string
     */
    private string $tableName = '';

    /**
     * The migration's label.
     *
     * @var string
     */
    private string $label = '';

    /**
     * The description of the migration.
     *
     * @var string
     */
    private string $description = '';

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Initialises the migration. This method is called automatically when the migration is instantiated.
     * 
     * For internal use only. Subclasses should override the configure() method to set the migration's description.
     *
     * @return void
     */
    final public function __init(): void {
        $this->__generateNamesFromFile();
        $this->configure();
    }

    /**
     * Should be used by subclasses to perform any necessary configuration for the migration.
     * Should be overridden in concrete migration classes to define migration-specific configuration.
     *
     * @return void
     */
    protected function configure(): void {}

    /**
     * Sets the migration's label. If not set, a label will be generated automatically based on the migration's name.
     *
     * @param string $label
     *
     * @return void
     */
    final protected function label(string $label): void {
        $this->label = $label;
    }

    /**
     * Sets the description of the migration.
     *
     * @param string $description
     *
     * @return void
     */
    final protected function description(string $description): void {
        $this->description = $description;
    }

    // =========================================================================
    // Operations
    // =========================================================================

    /**
     * Run the migrations
     * 
     * @param Table $table
     *
     * @return void
     */
   abstract public function up(Table $table): void;

    /**
     * Reverse the migrations
     * 
     * @param Table|string $table
     *
     * @return void
     */
    abstract public function down(Table|string $table): void;

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Gets the label of the migration.
     *
     * @return string
     */
    final public function getLabel(): string {
        return $this->label;
    }

    /**
     * Gets the description of the migration.
     *
     * @return string
     */
    final public function getDescription(): string {
        return $this->description;
    }

    /**
     * Gets migration's handle.
     *
     * @param bool $full Whether to return the full handle (with timestamp) or just the handle without timestamp.
     *
     * @return string
     */
    final public function getHandle(bool $full = true): string {
        return $full ? $this->fullHandle : $this->handle;
    }

    /**
     * Gets the name of the table being migrated.
     *
     * @return string
     */
    final public function getTableName(): string {
        return $this->tableName;
    }

    /**
     * Attempts to generate a handle for the migration based on its file name and identify the table name.
     * 
     * File paths should be structured as follows:
     * - For create migrations: /path/to/migrations/001_table_name/create_table_name.php
     * - For update migrations: /path/to/migrations/001_table_name/updates/timestamp_update_table_name.php
     *
     * @return void
     * @throws \RuntimeException if the migration's names cannot be generated from the file name.
     */
    private function __generateNamesFromFile(): void {
        $filePath = (new \ReflectionClass($this))->getFileName();

        $this->__generateTableName($filePath);

        $fileName = Str::replace('.php', '', basename($filePath));
        $fileNameWithoutTimestamp = preg_replace('/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', '', $fileName);

        if (Str::startsWith($fileNameWithoutTimestamp, ['create_', 'update_', 'add_', 'remove_'])) {
            $this->fullHandle = $fileName;
            $this->handle     = $fileNameWithoutTimestamp;
            return;
        }

        throw new \RuntimeException('Migration name not set. Please use the name() method in the init() method, or ensure the migration file name starts with "create_" or "update_" (after the timestamp).');
    }

    /**
     * Attempts to determine the name of the table being migrated/updated based on the file path if no table name has been set.
     * 
     * File paths should be structured as follows:
     * - For create migrations: /path/to/migrations/001_table_name/create_table_name.php
     * - For update migrations: /path/to/migrations/001_table_name/updates/timestamp_update_table_name.php
     *
     * @return void
     */
    private function __generateTableName(string $filePath): void {
        if ($this->tableName !== '') {
            return;
        }

        $fileDir  = dirname($filePath);

        $tableName = basename($fileDir) === 'updates' ? basename(dirname($fileDir)) : basename($fileDir);
        $tableNameWithoutPrefix = preg_replace('/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', '', $tableName);

        $this->tableName = $tableNameWithoutPrefix;
    }

    /**
     * Retrieves an existing label or attempts to generate one based on the migration's name.
     *
     * @return string
     */
    final protected function generateLabel(): string {
        if ($this->label !== '') {
            return $this->label;
        }

        if ($this->handle === '') {
            throw new \RuntimeException('Migration name not set. Cannot generate label.');
        }

        $this->label = Str::title(Str::replace(['_', '-'], ' ', $this->handle));
        return $this->label;
    }

    /**
     * Resolves the label and callback for the migration, allowing for flexible parameter passing.
     *
     * @param string|Closure $labelOrCallback The label or callback function.
     * @param Closure|null   $callback        The callback function (if not provided in the first parameter).
     *
     * @return array An array containing the resolved label and callback.
     * @throws \InvalidArgumentException if the callback is not provided.
     */
    final protected function resolveLabelAndCallback(string|Closure $labelOrCallback, ?Closure $callback): array {
        if ($labelOrCallback instanceof Closure) {
            $callback = $labelOrCallback;
            $label = $this->generateLabel();
        } else {
            $label = $labelOrCallback;
            $this->label($label);
        }

        if ($callback === null) {
            throw new \InvalidArgumentException('Callback must be provided when defining a migration operation.');
        }

        return [$label, $callback];
    }
}