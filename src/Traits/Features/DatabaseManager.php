<?php

namespace MM\Meros\Traits\Features;

use Illuminate\Support\Facades\File;

trait DatabaseManager {
    /**
     * Whether the feature has database tables to migrate.
     * 
     * @var boolean
     */
    private bool $hasMigrations = false;

    /**
     * The directory where migrations are stored.
     *
     * @var string
     */
    protected string $migrationsDir = 'Database/Migrations';

    /**
     * Migration classes discovered from the feature's migrations directory.
     *
     * @var array
     */
    protected array $discoveredMigrations = [];

    protected function loadMigrations(): void {
        if ($this->theme->allowsMigrations() === false) {
            return;
        }

        $migrationsPath = $this->path . $this->migrationsDir;
        if (!File::exists($migrationsPath) || !File::isDirectory($migrationsPath)) {
            return;
        }

        $migrationFiles = File::files($migrationsPath);

        foreach ($migrationFiles as $migrationFile) {
            $file = $migrationFile->getPathname();
            $this->theme->addMigrationFromPath($file, $this->hookPrefix);
        }

        $this->hasMigrations = count($migrationFiles) > 0;
    }
 }