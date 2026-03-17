<?php

namespace MM\Meros\App\Services\Theme\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration as LaravelMigration;

use MM\Meros\App\Facades\Theme;
use MM\Meros\App\Models\Migration;

trait MigrationManager {

    /**
     * Array of registered migrations, grouped by source and priority.
     * 
     * @var array
     */
    private array $registeredMigrations = [];

    /**
     * Whether migrations or rollbacks are currently being run.
     * 
     * @var boolean
     */
    private bool $isRunningMigrations = false;

    /**
     * Array of messages for migration operations.
     * 
     * @var array
     */
    private array $messages = [
        'core_migrations_not_set' => 'Meros core migrations have not been run. Please run the "wp meros setup-migrations" command to set up core migrations before running any other migrations.',
        'migrations_running'      => 'Cannot run migrations at this time.',
        'no_permission'           => 'The current user does not have permission to run migrations.',
        'no_migrations'           => 'No migrations to run.',
        'no_rollbacks'            => 'No rollbacks to run.',
        'slug_not_found'          => 'Migration with the provided slug was not found.',
        'slug_already_run'        => 'Migration with the provided slug has already been run.',
        'slug_not_run'            => 'Migration with the provided slug has not been run.',
        'core_migration_rollback' => 'The core migrations table cannot be rolled back.'
    ];

    /**
     * Sets up meros core migrations.
     * 
     * @return void
     */
    final public function setMerosCoreMigrations(): void {
        $migrations = File::files(
            trailingslashit(Theme::getFrameworkPath()) . 'database/migrations'
        );

        foreach($migrations as $migrationFile) {
            $this->registerMigrationFromPath($migrationFile->getPathname(), 'meros_core');
        }
    }

    /**
     * Registers a migration from a file path.
     *
     * @param string $path The file path to the migration.
     * @param string $source The source of the migration(e.g. theme or package name).
     * @return bool|array Returns migration config on success or false on failure.
    */
    final public function registerMigrationFromPath(string $path, string $source): bool|array {
        if (!File::exists($path) || File::isDirectory($path)) {
            return false;
        }

        $instance = include_once $path;
        $slug     = Str::beforeLast(basename($path), '.');

        if (!Str::contains($slug, ['create', 'update', 'remove'])) {
            return false;
        }

        $withoutTimestamp = preg_replace('/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', '', $slug);
        $type             = Str::before($withoutTimestamp, '_');

        if (!Str::contains($type, ['create', 'update', 'remove'])) {
            return false;
        }

        if (!($instance instanceof LaravelMigration)) {
            return false;
        }

        if (
            !method_exists($instance, 'up') || 
            !method_exists($instance, 'down')
        ) {
            return false;
        }

        $config = [
            'source'         => $source,
            'type'           => $type,
            'label'          => Str::title(Str::replace('_', ' ', $withoutTimestamp)),
            'slug'           => $slug,
            'path_reference' => $path,
            'instance'       => $instance,
        ];

        $this->registeredMigrations[$source][] = $config;
        return $config;
    }

    /**
     * Runs discovered migrations.
     * 
     * @return array|string Array of completed migration slugs, or error message string if migrations cannot be run.
     */
    final public function runMigrations(string $fromSource = ''): array|string {
        $shouldRun = $this->shouldRun(false, function() use ($fromSource) {
            $sourceInstalled = $this->checkServiceInstalled();
            if (!$sourceInstalled && $fromSource !== 'meros_core') {
                return $this->messages['core_migrations_not_set'];
            }

            return true;
        });

        if ($shouldRun !== true) {
            return $shouldRun;
        }

        $this->isRunningMigrations = true;

        // Get migrations ordered by priority
        $migrationsToRun = $this->getMigrationsToRun($fromSource, '', false);

        // Batch ID
        $batchId = Str::ulid();

        // Track completed migrations
        $completedMigrations = [];

        foreach($migrationsToRun as $migration) {
            if ($migration['slug'] === '') {
                continue;
            }

            if ($this->checkServiceInstalled() === true) {
                $migrationRecord = Migration::where(
                    'slug', $migration['slug']
                )->first();
                
                if ($migrationRecord) {
                    continue;
                }
            }

            $instance = $migration['instance'];
            $instance->up();

            Migration::create([
                'source'         => $migration['source'],
                'type'           => $migration['type'],
                'label'          => $migration['label'],
                'slug'           => $migration['slug'],
                'path_reference' => $migration['path_reference'],
                'batch_id'       => $batchId
            ]);

            $completedMigrations[] = $migration['slug'];
        }

        $this->isRunningMigrations = false;

        if ($completedMigrations === []) {
            return $this->messages['no_migrations'];
        }

        return $completedMigrations;
    }

    /**
     * Rolls back discovered migrations.
     * 
     * @param string $fromSource Optional source to roll back migrations from. If not provided, rolls back from all sources.
     * @param string $fromBatch Optional batch ID to roll back migrations from. If not provided, rolls back all batches.
     * @return array|string Array of rolled back migration slugs, or error message string if rollbacks cannot be run.
     */
    final public function rollbackMigrations(string $fromSource = '', string $fromBatch = ''): array|string {
        $shouldRun = $this->shouldRun();
        if ($shouldRun !== true) {
            return $shouldRun;
        }

        $this->isRunningMigrations = true;

        // Get migrations in reverse order to how they were run
        $migrationsToRun = $this->getMigrationsToRun($fromSource, $fromBatch, true);

        // Track rolled back migrations
        $rolledBackMigrations = [];

        foreach( $migrationsToRun as $migration) {
            if ($migration['slug'] === '') {
                continue;
            }

            $migrationRecord = Migration::where(
                'slug', $migration['slug']
            )->first();

            if (!$migrationRecord) {
                continue;
            }

            $instance = $migration['instance'];
            $instance->down();

            if ($migration['slug'] !== '001_create_meros_migrations_table') {
                $migrationRecord->delete();
            }

            $rolledBackMigrations[] = $migration['slug'];
        }

        $this->isRunningMigrations = false;

        if ($rolledBackMigrations === []) {
            return $this->messages['no_rollbacks'];
        }

        return $rolledBackMigrations;
    }

    /**
     * Rolls back the last migration that was run.
     * 
     * @return string Returns slug of rolled back migration, or error message string if rollbacks cannot be run.
     */
    final public function rollbackLastMigration(): string {
        $shouldRun = $this->shouldRun();
        if ($shouldRun !== true) {
            return $shouldRun;
        }

        $lastMigrationRecord = Migration::orderBy('id', 'desc')->first();

        if (!$lastMigrationRecord) {
            return $this->messages['no_rollbacks'];
        }

        return $this->rollbackMigrationFromSlug($lastMigrationRecord->source, $lastMigrationRecord->slug);
    }

    /**
     * Rolls back the last batch of migrations that were run.
     * 
     * @return array|string Array of rolled back migration slugs, or error message string if rollbacks cannot be run.
     */
    final public function rollbackLastMigrationBatch(): array|string {
        $shouldRun = $this->shouldRun();
        if ($shouldRun !== true) {
            return $shouldRun;
        }

        $lastBatchId = Migration::orderBy('created_at', 'desc')->value('batch_id');

        if (!$lastBatchId) {
            return $this->messages['no_rollbacks'];
        }

        return $this->rollbackMigrations('', $lastBatchId);
    }

    /**
     * Runs a migration using its classname.
     *
     * @param string $source
     * @param string $slug
     * @return string Returns slug of completed migration, or error message string if migration cannot be run.
     */
    final public function runMigrationFromSlug(string $source, string $slug): string {
        $shouldRun = $this->shouldRun();
        if ($shouldRun !== true) {
            return $shouldRun;
        }

        $this->isRunningMigrations = true;
        $migrationConfig = $this->getRegisteredMigrationFromSlug($source, $slug);

        if ($migrationConfig === null) {
            $this->isRunningMigrations = false;
            return $this->messages['slug_not_found'];
        }

        $migrationRecord = Migration::where(
            'slug', $migrationConfig['slug']
        )->first();

        if ($migrationRecord !== null) {
            $this->isRunningMigrations = false;
            return $this->messages['slug_already_run'];
        }

        $instance = new $migrationConfig['class'];
        $instance->up();

        Migration::create([
            'source'         => $migrationConfig['source'],
            'type'           => $migrationConfig['type'],
            'label'          => $migrationConfig['label'],
            'slug'           => $migrationConfig['slug'],
            'path_reference' => $migrationConfig['path_reference']
        ]);

        $this->isRunningMigrations = false;
        return 'Migration ' . $migrationConfig['slug'] . ' completed successfully.';
    }

    /**
     * Rolls back a migration using its slug.
     *
     * @param string  $source
     * @param string  $slug
     * @return string Returns slug of rolled back migration, or error message string if migration cannot be rolled back.
     */
    final public function rollbackMigrationFromSlug(string $source, string $slug): string {
        $shouldRun = $this->shouldRun(function () use ($slug) {
            if ($slug === 'create_db_tasks_table') {
                return $this->messages['core_migration_rollback'];
            }
        });

        if ($shouldRun !== true) {
            return $shouldRun;
        }

        $this->isRunningMigrations = true;
        $migrationConfig = $this->getRegisteredMigrationFromSlug($source, $slug);

        if ($migrationConfig === null) {
            $this->isRunningMigrations = false;
            return $this->messages['slug_not_found'];
        }

        $migrationRecord = Migration::where(
            'slug', $migrationConfig['slug']
        )->first();

        if ($migrationRecord === null) {
            $this->isRunningMigrations = false;
            return $this->messages['slug_not_run'];
        };

        $instance = new $migrationConfig['class'];
        $instance->down();

        $migrationRecord->delete();

        $this->isRunningMigrations = false;
        return 'Migration ' . $migrationConfig['slug'] . ' rolled back successfully.';
    }

    /**
     * Gets the discovered migrations, ordered by priority.
     * 
     * @param string $fromSource Optional source to get migrations from. If not provided, gets from all sources.
     * @param string $fromBatch Optional batch ID to get migrations from. If not provided, gets from all batches.
     * @param bool   $reverse Whether to order migrations in reverse (for rollbacks). Defaults to false.
     * @return array
     */
    private function getMigrationsToRun(string $fromSource = '', string $fromBatch = '', $reverse = false): array {
        $migrationsToRun = [];

        if ($fromSource !== '') {
            foreach($this->registeredMigrations as $source => $migrations) {
                if ($fromSource !== '' && $source !== $fromSource ) {
                    continue;
                }

                foreach($migrations as $migration) {
                    $migrationsToRun[] = $migration;
                }
            }
        }

        else if ($fromBatch !== '') {
            $migrationRecords = Migration::where('batch_id', $fromBatch)->get();
            $migrationRecordSlugs = $migrationRecords->pluck('slug')->toArray();

            foreach($this->registeredMigrations as $source => $migrations) {
                foreach($migrations as $migration) {
                    if (!in_array($migration['slug'], $migrationRecordSlugs)) {
                        continue;
                    }

                    $migrationsToRun[] = $migration;
                }
            }
        }

        else {
            foreach($this->registeredMigrations as $source => $migrations) {
                foreach($migrations as $migration) {
                    $migrationsToRun[] = $migration;
                }
            }
        }

        if ($reverse) {
            krsort($migrationsToRun);
        } else {
            ksort($migrationsToRun);
        }
        
        return $migrationsToRun;
    }

    /**
     * Gets registered migration config from slug.
     *
     * @param string $slug
     * @return array|null Returns migration config, or null if not found.
     */
    private function getRegisteredMigrationFromSlug(string $source, string $slug): ?array {
        $migrationsToRun = $this->getMigrationsToRun($source);
        foreach ($migrationsToRun as $migration) {
            if ($migration['slug'] === $slug) {
                return $migration;
            }
        }
        return null;
    }

    /**
     * Checks whether a migration task should be run.
     * Returns true if the migration task should be run, or a string message if it should not be run.
     *
     * @param  boolean       $checkService Whether to check if the meros core migrations have been run before allowing migrations to be run. Defaults to true.
     * @param  callable|null $callback Optional callback to run additional checks. Should return true if checks pass, or a string message if they fail.
     * @return string|boolean
     */
    private function shouldRun($checkService = true, callable|null $callback = null): string|bool {
        if (!current_user_can('manage_options')) {
            return $this->messages['no_permission'];
        }

        if ($this->isRunningMigrations) {
            return $this->messages['migrations_running'];
        }

        if ($checkService) {
            $serviceInstalled = $this->checkServiceInstalled();
            if ($serviceInstalled === false) {
                return $this->messages['core_migrations_not_set'];
            }
        }

        if (is_callable($callback)) {
            $callbackResult = call_user_func($callback);
            if ($callbackResult !== true) {
                return $callbackResult;
            }
        }

        return true;
    }

    /**
     * Checks whether the meros core migrations have been run.
     * 
     * @return boolean
     */
    private function checkServiceInstalled(): bool {
        if (!Schema::hasTable('meros_migrations')) {
            return false;
        }

        $coreMigrationRecord = Migration::where(
            'slug', 'like', '%create_meros_migrations_table'
        )->first();

        if ($coreMigrationRecord === null) {
            return false;
        }

        return true;
    }
 }