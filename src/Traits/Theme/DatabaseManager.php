<?php

namespace MM\Meros\Traits\Theme;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\Migration;
use MM\Meros\Helpers\ClassInfo;
use MM\Meros\Models\MerosMigration;
use MM\Meros\Database\Migrations\CreateMerosMigrationsTable;

trait DatabaseManager {
    /**
     * Whether to allow features to add database migrations.
     *
     * @var boolean
     */
    protected bool $allowDatabaseMigrations = true;

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
     * Gets whether the theme allows features to add database migrations.
     * 
     * @return bool
     */
    final public function allowsMigrations(): bool {
        return $this->allowDatabaseMigrations;
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
            $class->hasProperty('label', 'public', true) &&
            $class->hasProperty('priority', 'public', true)
        ) {
            if (!is_string($class->name::$label) || $class->name::$label === '') {
                return false;
            }

            $priority = $class->name::$priority;

            if (
                !is_int($priority) ||
                $priority < 100 ||
                $priority > 999
            ) {
                return false;
            }

            $this->migrations[$source][$class->name::$priority] = $class->name;
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
     * @return void
     */
    final public function runMigrations(string $fromSource = '', bool $coreFirstRun = false): void {
        if ($this->isRunningMigrations) {
            return;
        }

        $this->isRunningMigrations = true;

        // Get migrations ordered by priority
        $migrationsToRun = $this->getMigrationsToRun($fromSource);

        foreach($migrationsToRun as $_priorityGroup => $migrationClasses) {
            foreach ($migrationClasses as $migrationClass) {
                $pathReference = ClassInfo::get($migrationClass)->fullPath ?? '';

                if ($pathReference === '') {
                    continue;
                }

                if (!$coreFirstRun) {
                    $migrationRecord = MerosMigration::where(
                        'path_reference', $pathReference
                    )->first();
                    
                    if ($migrationRecord) {
                        continue;
                    }
                }

                $instance = new $migrationClass;
                $instance->up();

                MerosMigration::create([
                    'source'         => $fromSource,
                    'label'          => $migrationClass::$label,
                    'priority'       => $migrationClass::$priority,
                    'path_reference' => $pathReference
                ]);
            }
        }

        $this->isRunningMigrations = false;
    }

    /**
     * Rolls back discovered migrations.
     * 
     * @return void
     */
    final public function rollbackMigrations(string $fromSource = ''): void {
        if ($this->isRunningMigrations) {
            return;
        }

        $this->isRunningMigrations = true;

        // Get migrations in reverse order to how they were run
        $migrationsToRun = $this->getMigrationsToRun($fromSource, true);

        foreach( $migrationsToRun as $_priorityGroup => $migrationClasses) {
            foreach ($migrationClasses as $migrationClass) {
                $pathReference = ClassInfo::get($migrationClass)->fullPath ?? '';

                if ($pathReference === '') {
                    continue;
                }

                $migrationRecord = MerosMigration::where(
                    'path_reference', $pathReference
                )->first();

                if (!$migrationRecord) {
                    continue;
                }

                $instance = new $migrationClass;
                $instance->down();

                if ($migrationClass !== CreateMerosMigrationsTable::class) {
                    $migrationRecord->delete();
                }
            }
        }

        $this->isRunningMigrations = false;
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

            foreach($migrations as $priority => $migrationClass) {
                if ($migrationsToRun[$priority] ?? false) {
                    $migrationsToRun[$priority][] = $migrationClass;
                } else {
                    $migrationsToRun[$priority] = [$migrationClass];
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
     * @param string $migrationClass
     * @param string $source
     * @return void
     */
    final public function runMigrationFromClass(string $migrationClass, string $source): void {
        if ($this->isRunningMigrations) {
            return;
        }

        $this->isRunningMigrations = true;
        $classInfo = ClassInfo::get($migrationClass);

        if ($classInfo === false || !$classInfo->extends(Migration::class)) {
            return;
        }

        $pathReference = $classInfo->fullPath ?? '';
        
        if ($pathReference === '') {
            return;
        }

        $migrationRecord = MerosMigration::where(
            'path_reference', $pathReference
        )->first();

        if ($migrationRecord !== null) {
            return;
        }

        $instance = new $migrationClass;
        $instance->up();

        MerosMigration::create([
            'source' => $source,
            'label' => $migrationClass::$label,
            'priority' => $migrationClass::$priority,
            'path_reference' => $pathReference
        ]);

        $this->isRunningMigrations = false;
    }

    /**
     * Rolls back a migration using its classname.
     *
     * @param string $migrationClass
     * @return void
     */
    final public function rollbackMigrationFromClass(string $migrationClass): void {
        if ($migrationClass === CreateMerosMigrationsTable::class) {
            return;
        }

        if ($this->isRunningMigrations) {
            return;
        }

        $this->isRunningMigrations = true;

        $classInfo = ClassInfo::get($migrationClass);

        if ($classInfo === false || !$classInfo->extends(Migration::class)) {
            return;
        }

        $pathReference = $classInfo->fullPath ?? '';

        if ($pathReference === '') {
            return;
        }

        $migrationRecord = MerosMigration::where(
            'path_reference', $pathReference
        )->first();

        if ($migrationRecord === null) {
            return;
        };

        $instance = new $migrationClass;
        $instance->down();

        $migrationRecord->delete();

        $this->isRunningMigrations = false;
    }
}
