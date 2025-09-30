<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\Artisan;

trait DatabaseManager
{
    /**
     * Whether the feature has database tables to migrate.
     *
     * @var bool
     */
    protected bool $hasMigrations = false;

    /**
     * Whether to automatically run the feature's migrations
     * when the feature is initialised.
     *
     * @var bool
     */
    protected bool $autoMigrate = false;

    /**
     * The directory to search for migration files in relative to the
     * feature directory.
     *
     * @var string
     */
    protected string $migrationsDir = 'database/migrations';

    /**
     * Installs the migrations table.
     *
     * @return void
     */
    final public function installMigrationsTable(): void {
        Artisan::call('migrate:install');
    }

    /**
     * Runs the feature's migrations.
     *
     * @return void
     */
    final public function runMigrations(): void {
        $migrationPath = $this->getFeaturePath() . '/' . $this->migrationsDir;

        Artisan::call('migrate', [
            '--path'  => $migrationPath,
            '--force' => true,
        ]);
    }
}