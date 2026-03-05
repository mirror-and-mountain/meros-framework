<?php

namespace MM\Meros\Scripts;

use MM\Meros\Models\MerosMigration;

class MigrationCommands {
       /**
     * Runs feature database migrations on an environment.
     * A Wordpress user with 'manage_options' capability must run this command with the global --user flag set.
     *
     * ## OPTIONS
     *
     * <feature>
     * : The feature to run migrations for. This should be the name of a registered feature.
     * [<slug>]
     * : The slug of the migration to run. If not provided, all migrations for the feature will be run.
     * 
     * [--refresh]
     * : Whether to rollback migrations before running them again. (default: false)
     *
     * ## EXAMPLES
     *
     * wp meros:migration run my_feature --user=admin
     * wp meros:migration run my_feature my_migration_slug --user=admin
     * wp meros:migration run my_feature my_migration_slug --refresh --user=admin
     *
     * @subcommand run
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function runMigrations($args, $assoc_args) {
        // Parse arguments
        $feature = $args[0];
        if (isset($args[1])) {
            $slug = $args[1];
        } else {
            $slug = false;
        }

        if ($feature === 'meros_core') {
            \WP_CLI::error('Meros core migrations cannot be run using this command. Please use the "wp meros setup-migrations" command to run core migrations.');
            return;
        }
        
        $refresh = isset($assoc_args['refresh']) && $assoc_args['refresh'] === true ? true : false;

        $themeManager = app()->make('meros.theme_manager');

        if ($refresh) {
            $rollbackMsg = $slug !== false
                ? $themeManager->rollbackMigrationFromSlug($feature, $slug)
                : $themeManager->rollbackMigrations($feature);
            
            if (!is_array($rollbackMsg)) {
                \WP_CLI::error('A message was received while rolling back migrations: ' . $rollbackMsg);
                return;
            }

            $migrationMsg = $slug !== false 
                ? $themeManager->runMigrationFromSlug($feature, $slug)
                : $themeManager->runMigrations($feature);
            
            if (!is_array($migrationMsg) && $slug === false ) {
                \WP_CLI::warning('A message was received while running migrations: ' . $migrationMsg);
                return;
            } 
            
            else if (!is_array($migrationMsg) && $slug !== false && !str_contains($migrationMsg, 'successfully')) {
                \WP_CLI::warning('A message was received while running the migration: ' . $migrationMsg);
                return;
            } 
            
            else {
                \WP_CLI::success('Migrations run successfully.');
                return;
            }
        } 
        
        else {
            $migrationMsg = $slug !== false 
                ? $themeManager->runMigrationFromSlug($feature, $slug)
                : $themeManager->runMigrations($feature);
            
            if (!is_array($migrationMsg) && $slug === false) {
                \WP_CLI::warning('A message was received while running migrations: ' . $migrationMsg);
                return;
            }

            else if (!is_array($migrationMsg) && $slug !== false && !str_contains($migrationMsg, 'successfully')) {
                \WP_CLI::warning('A message was received while running the migration: ' . $migrationMsg);
                return;
            }
        }

        \WP_CLI::success('Migrations run successfully.');

    }

    /**
     * Rolls back feature database migrations on an environment.
     * A Wordpress user with 'manage_options' capability must run this command with the global --user flag set.
     *
     * ## OPTIONS
     *
     * <feature>
     * : The feature to run migrations for. This should be the name of a registered feature.
     * [<slug>]
     * : The slug of the migration to rollback. If not provided, all migrations for the feature will be rolled back.
     *
     * ## EXAMPLES
     *
     * wp meros:migration rollback my_feature --user=admin
     * wp meros:migration rollback my_feature my_migration_slug --user=admin
     *
     * @subcommand rollback
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function rollbackMigrations($args) {
        // Parse arguments
        $feature = $args[0];
        if (isset($args[1])) {
            $slug = $args[1];
        } else {
            $slug = false;
        }

        if ($feature === 'meros_core') {
            \WP_CLI::error('Meros core migrations cannot be rolled back individually. Please use the "wp meros setup-migrations" command with the --refresh flag to rollback and re-run core migrations.');
            return;
        }

        $themeManager = app()->make('meros.theme_manager');

        $rollbackMsg = $slug !== false 
            ? $themeManager->rollbackMigrationFromSlug($feature, $slug)
            : $themeManager->rollbackMigrations($feature);
        
        if (!is_array($rollbackMsg) && $slug === false) {
            \WP_CLI::error('A message was received while rolling back migrations: ' . $rollbackMsg);
            return;
        }

        else if (!is_array($rollbackMsg) && $slug !== false && !str_contains($rollbackMsg, 'successfully')) {
            \WP_CLI::error('A message was received while rolling back the migration: ' . $rollbackMsg);
            return;
        }

        \WP_CLI::success('Migrations rolled back successfully.');
    }

    /**
     * Rolls back the last migration batch.
     * A Wordpress user with 'manage_options' capability must run this command with the global --user flag set.
     *
     * ## EXAMPLES
     *
     * wp meros:migration rollback-last-batch --user=admin
     *
     * @subcommand rollback-last-batch
     *
     * @when after_wp_load
     *
     */
    public function rollbackLastMigrationBatch() {
        $themeManager = app()->make('meros.theme_manager');

        $rollbackMsg = $themeManager->rollbackLastMigrationBatch();
        
        if (!is_array($rollbackMsg)) {
            \WP_CLI::error('A message was received while rolling back the last migration batch: ' . $rollbackMsg);
            return;
        }

        \WP_CLI::success('Last migration batch rolled back successfully.');
    }

    /**
     * Rolls back the last migration.
     * A Wordpress user with 'manage_options' capability must run this command with the global --user flag set.
     *
     * ## EXAMPLES
     *
     * wp meros:migration rollback-last --user=admin
     *
     * @subcommand rollback-last
     *
     * @when after_wp_load
     *
     */
    public function rollbackLastMigration() {
        $themeManager = app()->make('meros.theme_manager');

        $rollbackMsg = $themeManager->rollbackLastMigration();
        
        if (!str_contains($rollbackMsg, 'successfully')) {
            \WP_CLI::error('A message was received while rolling back the last migration: ' . $rollbackMsg);
            return;
        }

        \WP_CLI::success('Last migration rolled back successfully.');
    }
    

    /**
     * Runs meros framework database migrations in the local environment.
     * A Wordpress user with 'manage_options' capability must run this command with the global --user flag set.
     *
     * ## OPTIONS
     * 
     * [--refresh]
     * : Whether to rollback migrations before running them again. (default: false)
     *
     * ## EXAMPLES
     *
     * wp meros:migration setup --user=admin
     * wp meros:migration setup --refresh --user=admin
     *
     * @subcommand setup
     *
     * @when after_wp_load
     * 
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function setupMigrations($args, $assoc_args) {
        // Parse arguments
        $refresh = isset($assoc_args['refresh']) && $assoc_args['refresh'] === true ? true : false;

        $themeManager = app()->make('meros.theme_manager');
        $themeManager->setMerosCoreMigrations();

        if ($refresh) {
            $migrationRecords = MerosMigration::where('source', '!=', 'meros_core')->get();
            if ($migrationRecords->count() > 0) {
                \WP_CLI::error('Meros core migrations cannot be rolled back because there are already non-core migrations that have been run. Please rollback all non-core migrations before re-running core migrations.');
                return;
            }
            $rollbackMsg = $themeManager->rollbackMigrations('meros_core');
            if (!is_array($rollbackMsg)) {
                \WP_CLI::error('A message was received while rolling back meros core migrations: ' . $rollbackMsg);
                return;
            }

            $migrationMsg = $themeManager->runMigrations('meros_core');
            if (!is_array($migrationMsg)) {
                \WP_CLI::warning('A message was received while running meros core migrations: ' . $migrationMsg);
                return;
            } else {
                \WP_CLI::success('Meros core migrations run successfully.');
                return;
            }
        } else {
            $migrationMsg = $themeManager->runMigrations('meros_core');
            if (!is_array($migrationMsg)) {
                \WP_CLI::warning('A message was received while running meros core migrations: ' . $migrationMsg);
                return;
            }
        }

        \WP_CLI::success('Meros core migrations run successfully.');
    }
}