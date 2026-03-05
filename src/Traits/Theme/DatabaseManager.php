<?php

namespace MM\Meros\Traits\Theme;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use MM\Meros\Contracts\Migration;
use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Models\MerosMigration;

trait DatabaseManager {
    /**
     * Whether to allow features to add database migrations.
     *
     * @var boolean
     */
    protected bool $allowDatabaseMigrations = true;

    /**
     * Whether to only allow database migrations to be run from WP CLI.
     *
     * @var boolean
     */
    protected bool $onlyAllowDatabaseMigrationsFromCli = false;

    /**
     * Whether the theme has database tables to migrate.
     * 
     * @var boolean
     */
    private bool $hasMigrations = false;

    /**
     * Indicates whether migrations are currently running.
     *
     * @var boolean
     */
    private bool $isRunningMigrations = false;

    /**
     * Migration classes discovered from features and the theme.
     * $source => $class format.
     *
     * @var array
     */
    protected array $migrations = [];

    /**
     * Messages for migration operations.
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
     * Gets whether the theme allows features to add database migrations.
     * 
     * @return bool
     */
    final public function allowsMigrations(): bool {
        return $this->allowDatabaseMigrations;
    }

    /**
     * Gets whether the theme only allows database migrations to be run from WP CLI.
     * 
     * @return bool
     */
    final public function onlyAllowsMigrationsFromCli(): bool {
        return $this->onlyAllowDatabaseMigrationsFromCli;
    }

    /**
     * Gets the discovered migrations.
     * 
     * @return array
     */
    final public function getMigrations(): array {
        return $this->migrations;
    }

    /**
     * Gets whether the theme has migrations to run.
     * 
     * @return bool
     */
    final public function hasMigrations(): bool {
        return $this->hasMigrations;
    }

    /**
     * Adds a migration to be run when runMigrations() is called.
     * 
     * @param string $path
     * @param string $source
     * @return bool True if migration was added successfully. False otherwise.
     */
    final public function addMigrationFromPath(string $path, string $source): bool {
        if ($this->allowDatabaseMigrations === false) {
            return false;
        }

        if (!File::exists($path) || File::isDirectory($path)) {
            return false;
        }

        include_once $path;
        $class = ClassInfo::getFromPath($path);
        if (
            $class !== false &&
            $class->extends(Migration::class) &&
            $class->hasMethod('up') &&
            $class->hasMethod('down') &&
            $class->hasProperty('slug', 'public', true) &&
            $class->hasProperty('priority', 'public', true)
        ) {
            $slug = $class->name::$slug;
            if (!is_string($slug) || $slug === '') {
                return false;
            }

            $label = $class->hasProperty('label', 'public', true) 
            && is_string($class->name::$label) 
            && $class->name::$label !== ''
                ? $class->name::$label 
                : Str::title(Str::replace('_', ' ', $class->name::$slug));

            $priority = $class->name::$priority;
            if (
                !is_int($priority) ||
                $priority < 100 ||
                $priority > 999
            ) {
                return false;
            }

            $this->migrations[$source][$priority] = [
                'source'   => $source,
                'label'    => $label,
                'slug'     => $slug,
                'priority' => $priority,
                'class'    => $class->name,
                'path_reference' => $path
            ];

            $this->hasMigrations = true;

            return true;
        }

        return false;
    }

    /**
     * Sets up meros core migrations.
     * 
     * @return void
     */
    final public function setMerosCoreMigrations(): void {
        $migrations = File::files(
            trailingslashit($this->themeDir) . 
            trailingslashit($this->frameworkDir) .
            'Database/Migrations'
        );

        foreach($migrations as $migrationFile) {
            $this->addMigrationFromPath($migrationFile->getPathname(), 'meros_core');
        }

        $this->hasMigrations = count($migrations) > 0;
    }
    
    /**
     * Runs discovered migrations.
     * 
     * @return array|string Array of completed migration slugs, or error message string if migrations cannot be run.
     */
    final public function runMigrations(string $fromSource = ''): array|string {
        if ($this->isRunningMigrations) {
            return $this->messages['migrations_running'];
        }

        if (!current_user_can('manage_options')) {
            return $this->messages['no_permission'];
        }


        $coreReady = $this->checkMerosCoreMigrationsSet();
        if ($coreReady === false && $fromSource !== 'meros_core') {
            return $this->messages['core_migrations_not_set'];
        }

        $this->isRunningMigrations = true;

        // Get migrations ordered by priority
        $migrationsToRun = $this->getMigrationsToRun($fromSource);

        // Track completed migrations
        $completedMigrations = [];

        foreach($migrationsToRun as $_priorityGroup => $migrationConfigurations) {
            foreach ($migrationConfigurations as $migrationConfig) {
                if ($migrationConfig['slug'] === '') {
                    continue;
                }

                if ($coreReady) {
                    $migrationRecord = MerosMigration::where(
                        'slug', $migrationConfig['slug']
                    )->first();
                    
                    if ($migrationRecord) {
                        continue;
                    }
                }

                $instance = new $migrationConfig['class'];
                $instance->up();

                MerosMigration::create([
                    'source'         => $migrationConfig['source'],
                    'label'          => $migrationConfig['label'],
                    'slug'           => $migrationConfig['slug'],
                    'priority'       => $migrationConfig['priority'],
                    'path_reference' => $migrationConfig['path_reference']
                ]);

                $completedMigrations[] = $migrationConfig['slug'];
            }
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
     * @return array|string Array of rolled back migration slugs, or error message string if rollbacks cannot be run.
     */
    final public function rollbackMigrations(string $fromSource = ''): array|string {
        if ($this->isRunningMigrations) {
            return $this->messages['rollbacks_running'];
        }

        if (!current_user_can('manage_options')) {
            return $this->messages['no_permission'];
        }

        if ($this->checkMerosCoreMigrationsSet() === false) {
            return $this->messages['core_migrations_not_set'];
        }

        $this->isRunningMigrations = true;

        // Get migrations in reverse order to how they were run
        $migrationsToRun = $this->getMigrationsToRun($fromSource, true);

        // Track rolled back migrations
        $rolledBackMigrations = [];

        foreach( $migrationsToRun as $_priorityGroup => $migrationConfigurations) {
            foreach ($migrationConfigurations as $migrationConfig) {
                if ($migrationConfig['slug'] === '') {
                    continue;
                }

                $migrationRecord = MerosMigration::where(
                    'slug', $migrationConfig['slug']
                )->first();

                if (!$migrationRecord) {
                    continue;
                }

                $instance = new $migrationConfig['class'];
                $instance->down();

                if ($migrationConfig['slug'] !== 'create_meros_migrations_table') {
                    $migrationRecord->delete();
                }

                $rolledBackMigrations[] = $migrationConfig['slug'];
            }
        }

        $this->isRunningMigrations = false;

        if ($rolledBackMigrations === []) {
            return $this->messages['no_rollbacks'];
        }

        return $rolledBackMigrations;
    }

    /**
     * Gets the discovered migrations, ordered by priority.
     * 
     * @param string $fromSource Optional source to get migrations from. If not provided, gets from all sources.
     * @param bool $reverse Whether to order migrations in reverse (for rollbacks). Defaults to false.
     * @return array
     */
    private function getMigrationsToRun(string $fromSource = '', $reverse = false): array {
        $migrationsToRun = [];

        foreach($this->migrations as $source => $migrations) {
            if ($fromSource !== '' &&
                $source !== $fromSource
            ) {
                continue;
            }

            foreach($migrations as $priority => $config) {
                if ($migrationsToRun[$priority] ?? false) {
                    $migrationsToRun[$priority][] = $config;
                } else {
                    $migrationsToRun[$priority] = [$config];
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
     * Runs a migration using its classname.
     *
     * @param string $slug
     * @return string Returns slug of completed migration, or error message string if migration cannot be run.
     */
    final public function runMigrationFromSlug(string $slug): string {
        if ($this->isRunningMigrations) {
            return $this->messages['migrations_running'];
        }

        if (!current_user_can('manage_options')) {
            return $this->messages['no_permission'];
        }

        if ($this->checkMerosCoreMigrationsSet() === false) {
            return $this->messages['core_migrations_not_set'];
        }

        $this->isRunningMigrations = true;
        $migrationConfig = $this->getRegisteredMigrationFromSlug($slug);

        if ($migrationConfig === null) {
            $this->isRunningMigrations = false;
            return $this->messages['slug_not_found'];
        }

        $migrationRecord = MerosMigration::where(
            'slug', $migrationConfig['slug']
        )->first();

        if ($migrationRecord !== null) {
            $this->isRunningMigrations = false;
            return $this->messages['slug_already_run'];
        }

        $instance = new $migrationConfig['class'];
        $instance->up();

        MerosMigration::create([
            'source'         => $migrationConfig['source'],
            'label'          => $migrationConfig['label'],
            'slug'           => $migrationConfig['slug'],
            'priority'       => $migrationConfig['priority'],
            'path_reference' => $migrationConfig['path_reference']
        ]);

        $this->isRunningMigrations = false;
        return 'Migration ' . $migrationConfig['slug'] . ' completed successfully.';
    }

    /**
     * Rolls back a migration using its slug.
     *
     * @param string $slug
     * @return string Returns slug of rolled back migration, or error message string if migration cannot be rolled back.
     */
    final public function rollbackMigrationFromSlug(string $slug): string {
        if ($slug === 'create_meros_migrations_table') {
            return $this->messages['core_migration_rollback'];
        }

        if ($this->isRunningMigrations) {
            return $this->messages['migrations_running'];
        }

        if (!current_user_can('manage_options')) {
            return $this->messages['no_permission'];
        }

        if ($this->checkMerosCoreMigrationsSet() === false) {
            return $this->messages['core_migrations_not_set'];
        }

        $this->isRunningMigrations = true;
        $migrationConfig = $this->getRegisteredMigrationFromSlug($slug);

        if ($migrationConfig === null) {
            $this->isRunningMigrations = false;
            return $this->messages['slug_not_found'];
        }

        $migrationRecord = MerosMigration::where(
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
     * Gets registered migration config from slug.
     *
     * @param string $slug
     * @return array|null Returns migration config, or null if not found.
     */
    private function getRegisteredMigrationFromSlug(string $slug): ?array {
        foreach ($this->migrations as $source => $migrations) {
            foreach ($migrations as $priority => $configs) {
                foreach ($configs as $config) {
                    if ($config['slug'] === $slug) {
                        return $config;
                    }
                }
            }
        }
        return null;
    }

    private function checkMerosCoreMigrationsSet(): bool {
        if (Schema::hasTable('db_migrations')) {
            $coreMigrationRecord = MerosMigration::where(
                'slug', 'create_meros_migrations_table'
            )->first();

            if ($coreMigrationRecord !== null) {
                return true;
            }
        }
        return false;
    }
}
