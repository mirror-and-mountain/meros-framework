<?php 

namespace MM\Meros\App\Support;

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

    public static function getTables(?string $connection = null): Collection {
        $schema     = static::schema($connection);
        $collection = collect(Arr::keyBy($schema->getTables(), 'name'));

        global $wpdb;
        $tablePrefix = $wpdb->prefix;

        $collection = $collection->map(function ($table) use ($tablePrefix) {
            $record = Migration::where(
                'related_table', Str::replace($tablePrefix, '', $table['name'])
            )->first();

            $table['source'] = $record !== null ? $record->source : 'wordpress/plugin';

            return $table;
        });

        return $collection;
    }

    public static function getTableData(string $table, ?string $connection = null): ?Collection {
        $schema = static::schema($connection);
        global $wpdb;
        $tablePrefix = $wpdb->prefix;
        $table = Str::replace($tablePrefix, '', $table);

        if ($schema->hasTable($table)) {
            return collect([
                'columns'      => $schema->getColumns($table),
                'indexes'      => $schema->getIndexes($table),
                'foreign_keys' => $schema->getForeignKeys($table),
            ]);
        }

        return null;
    }

    /**
     * @param  string                         $table
     * @param  string                         $installable
     * @param  Closure(Blueprint): void       $callback
     * @param  string|null                    $connection
     * 
     * @return void
     */
    public static function create(string $table, string $installable, Closure $callback, ?string $connection = null): void {
        $schema = static::schema($connection);

        $schema->create($table, $callback);

        event(new TableCreated($table, $installable, $connection));
    }

    /**
     * @param  string                         $table
     * @param  string                         $installable
     * @param  Closure(Blueprint): void       $callback
     * @param  string|null                    $connection
     * 
     * @return void
     */
    public static function table(string $table, string $installable, Closure $callback, ?string $connection = null): void {
        $schema = static::schema($connection);

        $schema->table($table, $callback);

        event(new TableUpdated($table, $installable, $connection));
    }

    /**
     * @param  string      $table
     * @param  string      $installable
     * @param  string|null $connection
     * 
     * @return void
     */
    public static function dropIfExists(string $table, string $installable, ?string $connection = null): void {
        $schema = static::schema($connection);

        if ($schema->hasTable($table)) {
            $schema->dropIfExists($table);

            event(new TableDropped($table, $installable, $connection));
        }
    }
}