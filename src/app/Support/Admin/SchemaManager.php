<?php 

namespace MM\Meros\App\Support\Admin;

use Closure;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use MM\Meros\App\Events\Migrations\TableCreated;
use MM\Meros\App\Events\Migrations\TableUpdated;
use MM\Meros\App\Events\Migrations\TableDropped;

class SchemaManager {
    /**
     * Get the schema builder for a given connection.
     *
     * @param  string|null $connection
     * @return \Illuminate\Database\Schema\Builder
     */
    protected static function schema(?string $connection = null) {
        return Schema::connection($connection);
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