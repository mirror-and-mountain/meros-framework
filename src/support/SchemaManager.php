<?php 

namespace MM\Meros\Support;

use Closure;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use MM\Meros\Contracts\Features\Data\Table;
use MM\Meros\App\Models\Migration;

final class SchemaManager {
    // =========================================================================
    // Schema Helpers
    // =========================================================================

    /**
     * Get the wp table prefix.
     *
     * @return string
     */
    public static function getTablePrefix(): string {
        global $wpdb;
        return $wpdb->prefix;
    }

    /**
     * Get the schema builder for a given connection.
     *
     * @param  string|null $connection
     * @return Builder
     */
    public static function schema(?string $connection = null): Builder {
        return Schema::connection($connection);
    }

    /**
     * Get a collection of all tables in the database, along with their provider.
     *
     * @param string|null $connection
     *
     * @return Collection
     */
    public static function getTables(?string $connection = null): Collection {
        $schema     = static::schema($connection);
        $collection = collect(Arr::keyBy($schema->getTables(), 'name'));

        if (!static::hasTable('meros_migrations', $connection)) {
            return $collection->map(function ($table) {
                $table['provider'] = 'wordpress/plugin';
                return $table;
            });
        }

        $tablePrefix = static::getTablePrefix();

        $collection = $collection->map(function ($table) use ($tablePrefix) {
            $record = Migration::where(
                'related_table', Str::replace($tablePrefix, '', $table['name'])
            )->first();

            $table['provider'] = $record !== null ? $record->provider : 'wordpress/plugin';

            return $table;
        });

        return $collection;
    }

    /**
     * Get the columns, indexes, and foreign keys for a given table, if it exists.
     *
     * @param string      $table
     * @param string|null $connection
     *
     * @return array|null
     */
    public static function getTableData(string $table, ?string $connection = null): ?array {
        $schema = static::schema($connection);
        
        $tablePrefix = static::getTablePrefix();
        $table       = Str::replace($tablePrefix, '', $table);

        if ($schema->hasTable($table)) {
            return [
                'columns'      => $schema->getColumns($table),
                'indexes'      => $schema->getIndexes($table),
                'foreign_keys' => $schema->getForeignKeys($table),
            ];
        }

        return null;
    }

    /**
     * Check if a table is installed (exists and has a creation record).
     *
     * @param string|Table $table
     * @param string|null  $connection
     *
     * @return boolean
     */
    public static function tableIsInstalled(string|Table $table, ?string $connection = null): bool {
        if ($table instanceof Table) {
            $tableName  = $table->getName();
            $connection = $table->getConnection();
        } else {
            $tableName = $table;
        }

        return 
            static::trackingTableExists() && 
            static::hasTable($tableName, $connection) &&
            static::getTableCreationRecord($tableName) !== null;
    }

    /**
     * Check if a specific update for a table is installed (exists and has an update record with no rollback).
     *
     * @param string|Table  $table
     * @param string        $handle
     * @param string|null   $connection
     *
     * @return boolean
     */
    public static function tableUpdateIsInstalled(string|Table $table, string $handle, ?string $connection = null): bool {
        if ($table instanceof Table) {
            $tableName  = $table->getName();
            $connection = $table->getConnection();
        } else {
            $tableName = $table;
        }

        return 
            static::tableIsInstalled($tableName, $connection) &&
            static::getTableUpdateRecord($tableName, $handle) !== null &&
            static::getTableRollbackRecord($tableName, $handle) === null;
    }

    /**
     * Check if all specified updates for a table are installed.
     *
     * @param string|Table $table
     * @param array        $handles
     * @param string|null  $connection
     *
     * @return boolean
     */
    public static function tableUpdatesAreInstalled(string|Table $table, array $handles, ?string $connection = null): bool {
        if ($table instanceof Table) {
            $tableName  = $table->getName();
            $connection = $table->getConnection();
        } else {
            $tableName = $table;
        }    

        foreach ($handles as $handle) {
            if (!static::tableUpdateIsInstalled($tableName, $handle, $connection)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a table exists in the database.
     *
     * @param string      $table
     * @param string|null $connection
     *
     * @return boolean
     */
    public static function hasTable(string $table, ?string $connection = null): bool {
        $schema = static::schema($connection);
        return $schema->hasTable($table);
    }

    /**
     * Get the creation record for a given table, if it exists.
     *
     * @param string $tableName
     *
     * @return Migration|null
     */
    public static function getTableCreationRecord(string $tableName): ?Migration {
        $tablePrefix = static::getTablePrefix();
        $tableName   = Str::replace($tablePrefix, '', $tableName);

        return Migration::where('related_table', $tableName)
            ->where('type', 'create')
            ->first();
    }

    /**
     * Get all update records for a given table, ordered by creation date.
     *
     * @param string $tableName
     *
     * @return Collection
     */
    public static function getTableUpdateRecords(string $tableName): Collection {
        $tablePrefix = static::getTablePrefix();
        $tableName   = Str::replace($tablePrefix, '', $tableName);

        return Migration::where('related_table', $tableName)
            ->whereIn('type', 'update')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get a specific update record for a given table and handle, if it exists.
     *
     * @param string $tableName
     * @param string $handle
     *
     * @return Migration|null
     */
    public static function getTableUpdateRecord(string $tableName, string $handle): ?Migration {
        $tablePrefix = static::getTablePrefix();
        $tableName   = Str::replace($tablePrefix, '', $tableName);

        return Migration::where('related_table', $tableName)
            ->where('handle', $handle)
            ->where('type', 'update')
            ->first();
    }

    /**
     * Get a specific rollback record for a given table and handle, if it exists.
     *
     * @param string $tableName
     * @param string $handle
     *
     * @return Migration|null
     */
    public static function getTableRollbackRecord(string $tableName, string $handle): ?Migration {
        $tablePrefix = static::getTablePrefix();
        $tableName   = Str::replace($tablePrefix, '', $tableName);

        return Migration::where('related_table', $tableName)
            ->where('handle', $handle . '_rollback')
            ->where('type', 'rollback')
            ->first();
    }

    /**
     * Check if the migrations tracking table exists in the database.
     *
     * @param string|null $connection
     *
     * @return boolean
     */
    public static function trackingTableExists(?string $connection = null): bool {
        $schema = static::schema($connection);
        return $schema->hasTable('meros_migrations');
    }

    // =========================================================================
    // Schema Operations
    // =========================================================================

    /**
     * @param string                    $label
     * @param string                    $handle
     * @param Table                     $table
     * @param Closure(Blueprint): void  $callback
     * @param array                     $dependencies
     * @param string|null               $connection
     * 
     * @return void
     */
    public static function create(
        string  $label,
        string  $handle,
        Table   $table,
        Closure $callback, 
        array   $dependencies = [],
        ?string $connection = null
    ): void {
        $tableName = $table->getName();
        $schema = static::schema($connection);

        $canRun = static::canRunOperation(
            $tableName, 
            $schema, 
            ['operation' => 'create', 'dependencies' => $dependencies], 
            true, 
            true
        );
        
        if ($canRun !== true) {
            throw new \RuntimeException("Cannot create table '$tableName'. Error: $canRun");
        }

        $schema->create($tableName, $callback);

        Migration::create([
            'provider'      => $table->getProvider()->getHandle(),
            'type'          => 'create',
            'label'         => $label,
            'handle'        => $handle,
            'related_table' => $tableName,
            'path'          => ltrim(Str::replace(get_stylesheet_directory(), '', $table->getMigrationPath()), '/'),
            'batch_id'      => $table->getBatchId()
        ]);
    }

    /**
     * @param string                   $label
     * @param string                   $handle
     * @param Table                    $table
     * @param Closure(Blueprint): void $callback
     * @param string|null              $connection
     * 
     * @return void
     */
    public static function update(
        string  $label,
        string  $handle, 
        Table   $table, 
        Closure $callback,
        ?string $connection = null
    ): void {
        $tableName = $table->getName();
        $schema = static::schema($connection);

        $canRun = static::canRunOperation($tableName, $schema, ['operation' => 'update', 'handle' => $handle], true, true);

        if ($canRun !== true) {
            throw new \RuntimeException("Cannot update table '$tableName' with handle '$handle'. Error: $canRun");
        }

        $schema->table($tableName, $callback);

        // Delete the rollback record if it exists.
        $rollbackRecord = static::getTableRollbackRecord($tableName, $handle);
        if ($rollbackRecord !== null) {
            $rollbackRecord->delete();
        }

        Migration::create([
            'provider'      => $table->getProvider()->getHandle(),
            'type'          => 'update',
            'label'         => $label,
            'handle'        => $handle,
            'related_table' => $tableName,
            'path'          => ltrim(Str::replace(get_stylesheet_directory(), '', $table->getMigrationPath()), '/'),
            'batch_id'      => $table->getBatchId()
        ]);
    }

    /**
     * @param string                    $label
     * @param string                    $handle
     * @param Table                     $table
     * @param Closure(Blueprint): void  $callback
     * @param string|null               $connection
     * 
     * @return void
     */
    public static function rollback(
        string  $label,
        string  $handle, 
        Table   $table, 
        Closure $callback,
        ?string $connection = null
    ): void {
        $tableName = $table->getName();
        $schema = static::schema($connection);

        $canRun = static::canRunOperation($tableName, $schema, ['operation' => 'rollback', 'handle' => $handle], true, true);

        if ($canRun !== true) {
            throw new \RuntimeException("Cannot rollback table '$tableName' with handle '$handle'. Error: $canRun");
        }

        $schema->table($tableName, $callback);

        // Remove the original update record.
        $updateRecord = static::getTableUpdateRecord($tableName, rtrim($handle, '_rollback'));
        if ($updateRecord !== null) {
            $updateRecord->delete();
        }

        Migration::create([
            'provider'      => $table->getProvider()->getHandle(),
            'type'          => 'rollback',
            'label'         => $label,
            'handle'        => $handle,
            'related_table' => $tableName,
            'path'          => ltrim(Str::replace(get_stylesheet_directory(), '', $table->getMigrationPath()), '/'),
            'batch_id'      => $table->getBatchId()
        ]);
    }

    /**
     * @param  Table|string $table
     * @param  string|null  $connection
     * 
     * @return void
     */
    public static function dropIfExists(
        Table|string  $table, 
        ?string       $connection = null
    ): void {
        $tableName = $table instanceof Table ? $table->getName() : $table;
        $schema = static::schema($connection);

        $canRun = static::canRunOperation($tableName, $schema, ['operation' => 'drop'], true, true);

        if ($canRun !== true) {
            throw new \RuntimeException("Cannot drop table '$tableName'. Error: $canRun");
        }

        $schema->dropIfExists($tableName);

        // Remove all migration records related to this table
        if ($tableName !== 'meros_migrations') {
            Migration::where('related_table', $tableName)->delete();
        }
    }

    /**
     * Determines if a table operation can be performed, optionally returning an error message if it cannot.
     *
     * @param string  $tableName
     * @param Builder $schema
     * @param array   $context
     * @param bool    $log
     * @param bool    $returnError
     *
     * @return bool|string
     */
    public static function canRunOperation(string $tableName, Builder $schema, array $context, bool $log = false, bool $returnError = false): bool|string {
        $logger = function ($message) use ($log) {
            if ($log) {
                Log::warning($message);
            }
        };
    
        $isTrackingTable = $tableName === 'meros_migrations';
        $userAllowed = current_user_can('manage_options');

        if (!$userAllowed) {
            $error = "Current user does not have permission to perform this operation on table '$tableName'.";
            $logger($error);
            return $returnError ? $error : false;
        }

        $correctUsage = is_admin() || app()->runningInConsole();

        if (!$correctUsage) {
            $error = "Table operations can only be performed in the admin area or via the console. Attempted operation on table '$tableName' outside of allowed context.";
            $logger($error);
            return $returnError ? $error : false;
        }

        if (!$isTrackingTable && !static::trackingTableExists($schema->getConnection()->getName())) {
            $error = "Migrations tracking table does not exist. Please ensure that the migrations tracking table is created before performing any operations.";
            $logger($error);
            return $returnError ? $error : false;
        }

        $operation      = $context['operation'] ?? null;
        $tableExists    = $schema->hasTable($tableName);
        $creationRecord = !$isTrackingTable || ($isTrackingTable && $operation === 'drop') ? static::getTableCreationRecord($tableName) : null;

        if ($operation === 'create') {
            $dependencies = $context['dependencies'] ?? [];

            if ($tableExists) {
                $error = "Table '$tableName' already exists in the database.";
                $logger($error);
                return $returnError ? $error : false;
            }

            if ($creationRecord !== null) {
                $error = "Table '$tableName' already has a creation record in the migrations tracking table.";
                $logger($error);
                return $returnError ? $error : false;
            }

            if (!empty($dependencies)) {
                foreach ($dependencies as $dependency) {
                    if (!static::tableIsInstalled($dependency)) {
                        $error = "Dependency table '$dependency' is not installed. Please ensure all dependencies are installed before creating table '$tableName'.";
                        $logger($error);
                        return $returnError ? $error : false;
                    }
                }
            }

            return true;
        }

        // For drop, update and rollback operations, the table must exist and have a creation record
        if (!$tableExists) {
            $error = "Table '$tableName' does not exist in the database.";
            $logger($error);
            return $returnError ? $error : false;
        }

        if ($creationRecord === null) {
            $error = "Table '$tableName' does not have a creation record in the migrations tracking table.";
            $logger($error);
            return $returnError ? $error : false;
        }

        if ($operation === 'drop') {
            return true;
        }

        // We'll check for the existence of the update record for both update and rollback operations
        $updateHandle = $context['handle'] ?? null;

        if ($operation === 'update') {
            if (static::tableUpdateIsInstalled($tableName, $updateHandle)) {
                $error = "Table '$tableName' already has an update record with handle '$updateHandle' in the migrations tracking table.";
                $logger($error);
                return $returnError ? $error : false;
            }

            return true;
        }

        if ($operation === 'rollback') {
            if (!static::tableUpdateIsInstalled($tableName, rtrim($updateHandle, '_rollback'))) {
                $error = "Table '$tableName' does not have an update record with handle '$updateHandle' in the migrations tracking table.";
                $logger($error);
                return $returnError ? $error : false;
            }

            $rollbackRecord = static::getTableRollbackRecord($tableName, Str::endsWith($updateHandle, '_rollback') ? $updateHandle : $updateHandle . '_rollback');

            if ($rollbackRecord !== null) {
                $error = "Table '$tableName' already has a rollback record with handle '$updateHandle' in the migrations tracking table.";
                $logger($error);
                return $returnError ? $error : false;
            }

            return true;
        }
        
        $error = "Invalid operation for table '$tableName'.";
        $logger($error);
        return $returnError ? $error : false;
    }
}