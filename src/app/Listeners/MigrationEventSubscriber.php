<?php 

namespace MM\Meros\App\Listeners;

use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\Log;

use MM\Meros\App\Events\Migrations\TableCreated;
use MM\Meros\App\Events\Migrations\TableUpdated;
use MM\Meros\App\Events\Migrations\TableRolledBack;
use MM\Meros\App\Events\Migrations\TableDropped;

use MM\Meros\App\Models\Migration;
use MM\Meros\Contracts\Features\Data\Table;

class MigrationEventSubscriber {
    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     *
     * @return array
     */
    public function subscribe(Dispatcher $events): array {
        return [
            TableCreated::class    => 'handleTableCreated',
            TableUpdated::class    => 'handleTableUpdated',
            TableDropped::class    => 'handleTableDropped',
            TableRolledBack::class => 'handleTableRolledBack',
        ];
    }

    /**
     * Handles table creation events.
     *
     * @param  TableCreated $event
     *
     * @return void
     */
    public function handleTableCreated(TableCreated $event): void {
        $tableName         = $event->tableName;
        $table             = $event->table;
        $connection        = $event->connection;

        if (!Schema::connection($connection)->hasTable($tableName)) {
            Log::warning("Table creation event received for non-existent table: $tableName, Connection: $connection");
            return;
        }

        if (!$this->hasMigrationsTable($connection)) {
            Log::warning("Migrations tracking table does not exist for connection: $connection. Cannot record migration for table: $tableName.");
            return;
        }

        $this->makeMigrationRecord($tableName, $table, 'create');
    }

    /**
     * Handles table update events.
     *
     * @param TableUpdated $event
     *
     * @return void
     */
    public function handleTableUpdated(TableUpdated $event): void {
        $tableName  = $event->tableName;
        $table      = $event->table;
        $connection = $event->connection;

        $tableName = $table->getHandle();

        if (!Schema::connection($connection)->hasTable($tableName)) {
            return;
        }

        if (!$this->hasMigrationsTable($connection)) {
            return;
        }

        $this->makeMigrationRecord($tableName, $table, 'update');
    }

    /**
     * Handles table rollback events.
     *
     * @param  TableRolledBack $event
     *
     * @return void
     */
    public function handleTableRolledBack(TableRolledBack $event): void {
        $tableName  = $event->tableName;
        $table      = $event->table;
        $connection = $event->connection;

        $tableName = $table->getHandle();

        if (!Schema::connection($connection)->hasTable($tableName)) {
            return;
        }

        if (!$this->hasMigrationsTable($connection)) {
            return;
        }

        $this->makeMigrationRecord($tableName, $table, 'rollback');
    }

    /**
     * Handles table drop events.
     *
     * @param TableDropped $event
     *
     * @return void
     */
    public function handleTableDropped(TableDropped $event): void {
        $tableName  = $event->tableName;
        $connection = $event->connection;

        if (Schema::connection($connection)->hasTable($tableName)) {
            return;
        }

        if (!$this->hasMigrationsTable($connection)) {
            return;
        }

        $records = Migration::where('related_table', $tableName)->get();
        
        foreach ($records as $record) {
            $record->delete();
        }
    }

    /**
     * Creates a migration record for the given table and operation type.
     *
     * @param  string $handle
     * @param  Table  $table
     * @param  string $type
     * @param  bool   $isRollback
     *
     * @return void
     */
    private function makeMigrationRecord(string $handle, Table $table, string $type, bool $isRollback = false): void {
        $label  = null;
        $path   = null;
    
        if ($type === 'create') {
            $handle = $type . '_' . $handle . '_table';
            $label  = $table->getLabel();
            $path   = $table->getMigrationPath();
        }

        else if ($type === 'update') {
            $updates = $table->getUpdates();

            if (isset($updates[$handle])) {
                $update = $updates[$handle];
                $label  = $update['label'] ?? $table->getLabel();
                $path   = $update['path'] ?? $table->getMigrationPath();

                if ($isRollback) {
                    $handle = $handle . '_rollback';
                }
            }
        }

        $trimmedPath = ltrim(Str::replace(get_stylesheet_directory(), '', $path), '/');

        if (isset($handle, $label, $path, $trimmedPath)) {
            Migration::create([
                'provider'      => $table->getProvider()->getHandle(),
                'type'          => $isRollback ? 'rollback' : $type,
                'label'         => $label,
                'handle'        => $handle,
                'related_table' => $table->getHandle(),
                'path'          => $trimmedPath,
                'batch_id'      => $table->getBatchId()
            ]);
        } else {
            Log::warning("Failed to create migration record for table: " . $table->getHandle() . ". Missing required data ($handle, $label, $path, $trimmedPath).");
        }

        // if ($type === 'update' && $isRollback) {
        //     $updateMigration = ;
        // }
    }

    /**
     * Returns whether the migrations tracking table exists for the given connection.
     *
     * @param string|null $connection
     * @return bool
     */
    private function hasMigrationsTable(?string $connection = null): bool {
        return Schema::connection($connection)->hasTable('meros_migrations');
    }
}