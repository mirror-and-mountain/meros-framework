<?php 

namespace MM\Meros\Support;

use Closure;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use MM\Meros\App\Events\Migrations\TableCreated;
use MM\Meros\App\Events\Migrations\TableUpdated;
use MM\Meros\App\Events\Migrations\TableDropped;

use MM\Meros\App\Models\Migration;

final class SchemaManager {
    /**
     * Get the schema builder for a given connection.
     *
     * @param  string|null $connection
     * @return \Illuminate\Database\Schema\Builder
     */
    private static function schema(?string $connection = null) {
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

        global $wpdb;
        $tablePrefix = $wpdb->prefix;

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
        
        global $wpdb;

        $tablePrefix = $wpdb->prefix;
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
     * @param  string                         $table
     * @param  string                         $installerHandle
     * @param  Closure(Blueprint): void       $callback
     * @param  string|null                    $connection
     * 
     * @return void
     */
    public static function create(string $table, string $installerHandle, Closure $callback, ?string $connection = null): void {
        $schema = static::schema($connection);

        $schema->create($table, $callback);

        event(new TableCreated($table, $installerHandle, $connection));
    }

    /**
     * @param  string                         $table
     * @param  string                         $installerHandle
     * @param  Closure(Blueprint): void       $callback
     * @param  string|null                    $connection
     * 
     * @return void
     */
    public static function table(string $table, string $installerHandle, Closure $callback, ?string $connection = null): void {
        $schema = static::schema($connection);

        $schema->table($table, $callback);

        event(new TableUpdated($table, $installerHandle, $connection));
    }

    /**
     * @param  string      $table
     * @param  string      $installerHandle
     * @param  string|null $connection
     * 
     * @return void
     */
    public static function dropIfExists(string $table, string $installerHandle, ?string $connection = null): void {
        $schema = static::schema($connection);

        if ($schema->hasTable($table)) {
            $schema->dropIfExists($table);

            event(new TableDropped($table, $installerHandle, $connection));
        }
    }
}