<?php

namespace MM\Meros\Scripts;

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
     * [--add-drop-table]
     * : Whether to add DROP TABLE statements before CREATE TABLE statements (default: false).
     * 
     * [--theme-options]
     * : Whether to clone registered theme options as well (default: false).
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
        
        $excludedTables = isset($assoc_args['except']) ? explode(',', $assoc_args['except']) : [];
        $dropTables     = isset($assoc_args['add-drop-table']) && $assoc_args['add-drop-table'] === true ? true : false;
        $themeOptions   = isset($assoc_args['theme-options']) && $assoc_args['theme-options'] === true ? true : false;

        // Get Environment Manager
        $manager = EnvironmentManager::get($from);

        // Sync table
        $status = $manager->syncTables($to, $tables, $excludedTables, $dropTables, $themeOptions);
        if ($status === false) {
            \WP_CLI::error($manager->getError());
        } else {
            \WP_CLI::success(sprintf('Table "%s" sync from "%s" to "%s" completed successfully.', implode(',', $tables), $from, $to));
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

        // Get Environment Manager
        $manager = EnvironmentManager::get($from);

        // Sync theme
        $status = $manager->syncTheme($to, $makeDir, $activate);
        if ($status === false) {
            \WP_CLI::error($manager->getError());
        } else {
            \WP_CLI::success(sprintf('Theme sync from "%s" to "%s" completed successfully.', $from, $to));
        }
    }
}
