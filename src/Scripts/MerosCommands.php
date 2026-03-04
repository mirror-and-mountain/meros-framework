<?php

namespace MM\Meros\Scripts;

use MM\Meros\Models\MerosMigration;

class MerosCommands {
    /**
     * Clones content from one environment to another environment.
     *
     * ## OPTIONS
     *
     * <tables>...
     * : The database tables to clone (comma-separated).
     * <to>
     * : The environment to clone to.
     * [<from>]
     * : The environment to clone from (default: local_dev).
     * 
     * [--except=<tables>]
     * : A comma-separated list of tables to exclude when using --all (default: none).
     * 
     * [--skip-global-excludes]
     * : Whether to skip globally excluded tables when using --all (default: false).
     * 
     * [--theme-options]
     * : Whether to clone registered theme options as well (default: false).
     * 
     * [--search-replace]
     * : Whether to perform search and replace on the cloned data (default: true).
     * 
     * [--add-drop-table]
     * : Whether to add DROP TABLE statements before CREATE TABLE statements (default: false).
     * 
     * 
     * ## EXAMPLES
     *
     * wp meros sync-table posts staging
     * wp meros sync-table posts development production
     * wp meros sync-table --all staging
     *
     * @subcommand sync-tables
     *
     * @when after_wp_load
     *
     * @param array $args Positional arguments.
     */
    public function syncTables($args, $assoc_args) {
        // Parse arguments
        $tables = explode(',', $args[0]);
        $from   = isset($args[2]) ? $args[2] : 'local_dev';
        $to     = $args[1];
        
        // Determine flags
        $excludedTables     = isset($assoc_args['except']) ? explode(',', $assoc_args['except']) : [];
        $skipGlobalExcludes = isset($assoc_args['skip-global-excludes']) && $assoc_args['skip-global-excludes'] === true ? true : false;
       
        $themeOptions = isset($assoc_args['theme-options']) && $assoc_args['theme-options'] === true ? true : false;
        $dropTables   = isset($assoc_args['add-drop-table']) && $assoc_args['add-drop-table'] === true ? true : false;
        $searchReplace = isset($assoc_args['search-replace']) && $assoc_args['search-replace'] === false ? false : true;

        // Confirm action
        \WP_CLI::warning(sprintf('You are about to synchronise tables from "%s" to "%s" with the following settings:', $from, $to));
        \WP_CLI::line('Tables to sync: ' . implode(', ', $tables));
        if (!empty($excludedTables)) {
            \WP_CLI::line('Excluded tables: ' . implode(', ', $excludedTables));
        }
        \WP_CLI::line('Skip global excludes: ' . ($skipGlobalExcludes ? 'Yes' : 'No'));
        \WP_CLI::line('Sync theme options: ' . ($themeOptions ? 'Yes' : 'No'));
        \WP_CLI::line('Add DROP TABLE statements: ' . ($dropTables ? 'Yes' : 'No'));
        \WP_CLI::line('Perform search and replace: ' . ($searchReplace ? 'Yes' : 'No'));
        \WP_CLI::line('This operation will overwrite data on the destination environment.');
        \WP_CLI::line('It is highly recommended to back up your database before proceeding.');
        \WP_CLI::confirm('Are you sure you want to proceed?', $assoc_args = []);

        // Get Environment Manager
        $manager = EnvironmentManager::get($from);

        // Sync table(s)
        $status = $manager->syncTables(
            $to, 
            $tables, 
            $excludedTables, 
            $skipGlobalExcludes, 
            $themeOptions, 
            $dropTables,
            $searchReplace
        );

        if ($status === false) {
            $error = $manager->getError();
            if (str_contains($error, 'cancelled')) {
                \WP_CLI::success($error);
            } else {
                \WP_CLI::error($error);
            }
        } else {
            \WP_CLI::success(sprintf('Tables synchronised from "%s" to "%s" successfully.', $from, $to));
        }
    }

    /**
     * Creates a new Meros Theme feature.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the feature to create. This will be converted to StudlyCase.
     *
     *
     * ## EXAMPLES
     *
     * wp meros create-feature MyNewFeature
     * wp meros create-feature my-new-feature
     *
     * @subcommand create-feature
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function createFeature($args) {
        // Parse arguments
        [$featureName] = $args;

        // Get Environment Manager
        $manager = EnvironmentManager::get('local_dev');

        // Create feature
        $status = $manager->addFeature($featureName);
        if ($status === false) {
            \WP_CLI::error($manager->getError());
        } else {
            \WP_CLI::success(sprintf('Feature "%s" created successfully.', $featureName));
        }
    }

    /**
     * Creates an ssh session on a remote environment.
     *
     * ## OPTIONS
     *
     * <environment>
     * : The environment to connect to via SSH.
     *
     * ## EXAMPLES
     *
     * wp meros connect-env production
     *
     * @subcommand connect-env
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function connectToEnvironment($args) {
        // Parse arguments
        $environmentName = $args[0];

        // Get Environment Manager
        $manager = EnvironmentManager::get($environmentName);

        // Connect to environment
        $status = $manager->connect();
        if ($status === false) {
            \WP_CLI::error($manager->getError());
        }
    }

    /**
     * Syncs a theme from and to an environment.
     *
     * ## OPTIONS
     *
     * <to>
     * : The environment to sync the theme to.
     * [<from>]
     * : The environment to sync the theme from (default: local_dev).
     *
     * [--activate]
     * : Whether to activate the theme after syncing. (default: true)
     *
     * [--make-dir]
     * : Whether to create the destination directory if it does not exist. (default: true)
     *
     * ## EXAMPLES
     *
     * wp meros sync-theme production
     * wp meros sync-theme staging development
     *
     * @subcommand sync-theme
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function syncTheme($args, $assoc_args) {
        // Parse arguments
        $from = $args[1] ?? 'local_dev';
        $to   = $args[0];

        // Determine flags
        $makeDir  = isset($assoc_args['make-dir']) && $assoc_args['make-dir'] === false ? false : true;
        $activate = isset($assoc_args['activate']) && $assoc_args['activate'] === false ? false : true;

        // Confirm action
        \WP_CLI::warning(sprintf('You are about to synchronise the theme from "%s" to "%s".', $from, $to));
        \WP_CLI::line('This operation will overwrite the theme in the destination environment.');
        \WP_CLI::line('It is highly recommended to back up your theme files before proceeding.');
        \WP_CLI::confirm('Are you sure you want to proceed?', $assoc_args = []);

        // Get Environment Manager
        $manager = EnvironmentManager::get($from);

        // Sync theme
        $status = $manager->syncTheme($to, $makeDir, $activate);
        if ($status === false) {
            $error = $manager->getError();
            if (str_contains($error, 'cancelled')) {
                \WP_CLI::success($error);
            } else {
                \WP_CLI::error($error);
            }
        } else {
            \WP_CLI::success(sprintf('Theme sync from "%s" to "%s" completed successfully.', $from, $to));
        }
    }

    /**
     * Runs feature database migrations on an environment.
     *
     * ## OPTIONS
     *
     * <feature>
     * : The feature to run migrations for. This should be the name of a registered feature.
     * [<environment>]
     * : The environment to run the migrations on (default: local_dev).
     * 
     * [--refresh]
     * : Whether to rollback migrations before running them again. (default: false)
     *
     * ## EXAMPLES
     *
     * wp meros run-migrations my_feature
     * wp meros run-migrations my_feature staging
     * wp meros run-migrations my_feature staging --refresh
     *
     * @subcommand run-migrations
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function runMigrations($args, $assoc_args) {
        // Parse arguments
        $feature = $args[0];
        if (!isset($args[1])) {
            $environment = 'local_dev';
        } else {
            $environment = $args[1] === 'local' ? 'local_dev' : $args[1];
        }

        if ($feature === 'meros_core') {
            \WP_CLI::error('Meros core migrations cannot be run using this command. Please use the "wp meros run-meros-migrations" command to run core migrations.');
            return;
        }
        
        $refresh = isset($assoc_args['refresh']) && $assoc_args['refresh'] === true ? true : false;

        $themeManager = app()->make('meros.theme_manager');

        if ($refresh) {
            $themeManager->rollbackMigrations($feature);
            $themeManager->runMigrations($feature);
        } else {
            $themeManager->runMigrations($feature);
        }

        \WP_CLI::success('Meros core migrations run successfully.');

    }

    /**
     * Rolls back feature database migrations on an environment.
     *
     * ## OPTIONS
     *
     * <feature>
     * : The feature to run migrations for. This should be the name of a registered feature.
     * [<environment>]
     * : The environment to run the migrations on (default: local_dev).
     *
     * ## EXAMPLES
     *
     * wp meros rollback-migrations my_feature
     * wp meros rollback-migrations my_feature staging
     *
     * @subcommand rollback-migrations
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function rollbackMigrations($args) {
        // Parse arguments
        $feature = $args[0];
        if (!isset($args[1])) {
            $environment = 'local_dev';
        } else {
            $environment = $args[1] === 'local' ? 'local_dev' : $args[1];
        }

        if ($feature === 'meros_core') {
            \WP_CLI::error('Meros core migrations cannot be rolled back individually. Please use the "wp meros run-meros-migrations" command with the --refresh flag to rollback and re-run core migrations.');
            return;
        }

        $themeManager = app()->make('meros.theme_manager');
        $themeManager->rollbackMigrations($feature);
        \WP_CLI::success('Meros core migrations rolled back successfully.');
    }

    /**
     * Runs meros framework database migrations in the local environment.
     *
     * ## OPTIONS
     * 
     * [<environment>]
     * : The environment to run the migrations on (default: local_dev).
     * 
     * [--refresh]
     * : Whether to rollback migrations before running them again. (default: false)
     *
     * ## EXAMPLES
     *
     * wp meros run-meros-migrations staging
     * wp meros run-meros-migrations staging --refresh
     *
     * @subcommand run-meros-migrations
     *
     * @when after_wp_load
     * 
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function runMerosMigrations($args, $assoc_args) {
        // Parse arguments
        if (!isset($args[1])) {
            $environment = 'local_dev';
        } else {
            $environment = $args[1] === 'local' ? 'local_dev' : $args[1];
        }

        $refresh = isset($assoc_args['refresh']) && $assoc_args['refresh'] === true ? true : false;

        $themeManager = app()->make('meros.theme_manager');
        $themeManager->setMerosCoreMigrations();

        if ($refresh) {
            $migrationRecords = MerosMigration::where('source', '!=', 'meros_core')->get();
            if ($migrationRecords->count() > 0) {
                \WP_CLI::error('Meros core migrations cannot be rolled back because there are already non-core migrations that have been run. Please rollback all non-core migrations before re-running core migrations.');
                return;
            }
            $themeManager->rollbackMigrations('meros_core');
            $themeManager->runMigrations('meros_core', true);
        } else {
            $themeManager->runMigrations('meros_core');
        }

        \WP_CLI::success('Meros core migrations run successfully.');
    }
}
