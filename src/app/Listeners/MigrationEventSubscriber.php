<?php 

namespace MM\Meros\App\Listeners;

use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Events\Migrations\TableCreated;
use MM\Meros\App\Events\Migrations\TableUpdated;
use MM\Meros\App\Events\Migrations\TableDropped;

use MM\Meros\App\Models\Migration;

use MM\Meros\App\Facades\Theme;
use MM\Meros\App\Facades\Registry;

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
        $installableHandle = $event->installable;
        $connection        = $event->connection;

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $this->makeMigrationRecord($table, $installableHandle);
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
        $installableHandle = $event->installable;
        $connection        = $event->connection;

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        $this->makeMigrationRecord($table, $installableHandle);
    }

    /**
     * Handles table drop events.
     *
     * @param  TableDropped $event
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
     * @param  string $installableHandle
     *
     * @return void
     */
    private function makeMigrationRecord(string $table, string $installableHandle): void {
        $installable = Registry::getInstallables()->firstWhere('handle', $installableHandle);

        if ($installable === null) {
            return;
        }

        $trimmedPath = Str::replace(Theme::getPath(), '', $installable->path);

        $record = Migration::create([
            'source'        => $installable->source->handle,
            'type'          => $installable->type,
            'subtype'       => $installable->subtype,
            'label'         => $installable->label,
            'handle'        => $installable->handle,
            'related_table' => $table,
            'path'          => $trimmedPath,
            'batch_id'      => $installable->currentBatchId
        ]);

        if ($record !== null) {
            $installable->installedTime = $record->created_at->format('d-m-Y H:i:s');
            $installable->isInstalled = true;
        }
    }
}