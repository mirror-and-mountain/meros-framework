<?php 

namespace MM\Meros\Scripts;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use MM\Meros\App\Facades\Theme;

class MakeCommands {
    /**
     * Makes a new migration file.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the migration. This should be in snake_case and describe the migration's purpose, e.g. "create_users_table".
     * 
     * 
     * ## EXAMPLES
     *
     * wp meros:make migration create_contacts_table
     *
     * @subcommand migration
     *
     * @when after_wp_load
     *
     * @param array $args Positional arguments.
     */
    public function makeMigration($args) {
        // Parse arguments
        [$migrationName] = $args;
        $migrationName   = Str::snake($migrationName);

        $frameworkDir  = Theme::getFrameworkPath();
        $migrationsDir = Theme::getMigrationsDir();

        if (!File::isDirectory($migrationsDir)) {
            File::makeDirectory($migrationsDir, 0755, true);
        }

        $stub = $frameworkDir . 'stubs/Migration.stub';

        if (!File::exists($stub)) {
            \WP_CLI::error('Migration stub file not found.');
            return;
        }

        $timestamp = date('Y_m_d_His');
        $migrationFileName = $timestamp . '_' . $migrationName . '.php';

        $migrationFilePath = $migrationsDir . '/' . $migrationFileName;
        if (File::exists($migrationFilePath)) {
            \WP_CLI::error('A migration with this name already exists.');
            return;
        }

        // Create migration file from stub
        $result = File::copy($stub, $migrationFilePath);

        if (!$result) {
            \WP_CLI::error('Failed to create the migration file.');
            return;
        }

        \WP_CLI::success(sprintf('Migration "%s" created successfully at "%s".', $migrationFileName, $migrationFilePath));
    }
}