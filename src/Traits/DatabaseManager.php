<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\Artisan;

trait DatabaseManager {
    /**
     * Whether the feature has database tables to migrate.
     * 
     * @var boolean
     */
    public bool $hasMigrations = false;

    /**
     * The directory to search for migration files in relative to the
     * feature directory.
     * 
     * @var string
     */
    protected string $migrationsDir = 'database/migrations';

    /**
     * Runs the feature's migrations.
     * 
     * @return void
     */
    final public function runMigrations(): void {
        $migrationsPath =
            trailingslashit('App') .
            trailingslashit('Features') .
            trailingslashit(ucfirst($this->getName())) .
            $this->migrationsDir;

        Artisan::call('migrate', [
            '--path' => $migrationsPath,
            '--force' => true,
        ]);
    }
}
