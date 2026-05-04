<?php 

namespace MM\Meros\App\Listeners;

use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Events\Migrations\TableCreated;
use MM\Meros\App\Events\Migrations\TableUpdated;
use MM\Meros\App\Events\Migrations\TableDropped;

use MM\Meros\App\Models\Migration;

use MM\Meros\Facades\Theme;
use MM\Meros\Facades\Tables;

class MigrationEventSubscriber {
    /**
     * Handles table creation events.
     *
     * @param  TableCreated $event
     *
     * @return void
     */
    public function handleTableCreated(TableCreated $event): void {
        $table             = $event->table;
        $installerHandle   = $event->installerHandle;
        $connection        = $event->connection;

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $this->makeMigrationRecord($table, 'create', $installerHandle);
    }

    /**
     * Handles table update events.
     *
     * @param  TableUpdated $event
     *
     * @return void
     */
    public function handleTableUpdated(TableUpdated $event): void {
        $table             = $event->table;
        $installerHandle   = $event->installerHandle;
        $connection        = $event->connection;

        if (!Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $this->makeMigrationRecord($table, 'update', $installerHandle);
    }

    /**
     * Handles table drop events.
     *
     * @param TableDropped $event
     *
     * @return void
     */
    public function handleTableDropped(TableDropped $event): void {
        $table      = $event->table;
        $connection = $event->connection;

        if (Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $records = Migration::where('related_table', $table)->get();
        
        foreach ($records as $record) {
            $record->delete();
        }
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     *
     * @return array
     */
    public function subscribe(Dispatcher $events): array {
        return [
            TableCreated::class => 'handleTableCreated',
            TableUpdated::class => 'handleTableUpdated',
            TableDropped::class => 'handleTableDropped',
        ];
    }

    /**
     * Creates a migration record for the given table and installable handle.
     *
     * @param  string $table
     * @param  string $installerHandle
     *
     * @return void
     */
    private function makeMigrationRecord(string $table, string $type, string $installerHandle): void {
        $installer = Tables::get($installerHandle);

        if ($installer === null) {
            return;
        }

        $trimmedPath = ltrim(Str::replace(get_stylesheet_directory(), '', $installer->getPath()), '/');

        Migration::create([
            'provider'      => $installer->provider->getHandle(),
            'type'          => $type,
            'label'         => $installer->getLabel(),
            'handle'        => $installer->getHandle(),
            'related_table' => $table,
            'path'          => $trimmedPath,
            'batch_id'      => $installer->getBatchID()
        ]);
    }
}