<?php

namespace MM\Meros\Scripts;

class MerosCommands {
    /**
     * Clones content from one environment to another environment.
     *
     * ## OPTIONS
     *
     * <to>
     * : The environment to clone to.
     * [<from>]
     * : The environment to clone from.
     * 
     * 
     * ## EXAMPLES
     *
     * wp meros clone-content staging
     * wp meros clone-content staging development
     *
     * @subcommand clone-content
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     */
    public function cloneContent($args) {

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
     * : The environment to sync the theme from.
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
        $to = $args[0] === 'local' ? 'local_dev' : $args[0];

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
