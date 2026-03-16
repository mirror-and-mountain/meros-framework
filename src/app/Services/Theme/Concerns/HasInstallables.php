<?php 

namespace MM\Meros\App\Services\Theme\Concerns;

use Illuminate\Support\Facades\File;
use MM\Meros\App\Facades\Admin;

trait HasInstallables {
    /**
     * Whether the item has database tables to migrate.
     * 
     * @var boolean
     */
    private bool $hasMigrations = false;

    /**
     * The directory where migrations are stored.
     *
     * @var string
     */
    protected string $migrationsDir = 'database/migrations';

    /**
     * An array of registered migration file paths.
     *
     * @var array
     */
    protected array $registeredMigrations = [];

    /**
     * Registers a migration file from a given path.
     *
     * @param string $type
     * @param string $path
     * @param string $source
     * @return array|bool
     */
    protected function registerMigrations(): void {
        if (!is_admin()) {
            return;
        }
        
        $migrationsPath = $this->path . $this->migrationsDir;

        if (!File::exists($migrationsPath) || !File::isDirectory($migrationsPath)) {
            return;
        }

        $migrationFiles = File::files($migrationsPath);

        foreach ($migrationFiles as $migrationFile) {
            $file   = $migrationFile->getPathname();
            $config = Admin::registerMigrationFromPath($file, $this->slug);
            
            if (is_array($config)) {
                $this->registeredMigrations[$config['slug']] = $config;
            }
        }

        $this->hasMigrations = count($this->registeredMigrations) > 0;
    }
}