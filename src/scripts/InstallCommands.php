<?php

namespace MM\Meros\Scripts;

use MM\Meros\App\Facades\Theme;
use MM\Meros\App\Facades\Registry;
use MM\Meros\Scripts\Concerns\HasInstallableActions;

class InstallCommands {

    use HasInstallableActions;

    /**
     * Installs the theme's migrations, or a specific migration for the theme, with the option to refresh them.
     *
     * ## OPTIONS
     *
     * [<installable>]
     * : The handle of the migration to run. If not provided, all migrations for the theme will be run.
     * 
     * [--refresh]
     * : Whether to rollback migrations before running them again. (default: false)
     *
     * ## EXAMPLES
     *
     * wp meros:install theme --user=admin
     * wp meros:install theme my_migration_slug --user=admin
     * wp meros:install theme my_migration_slug --refresh --user=admin
     *
     * @subcommand theme
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function theme($args, $assoc_args) {
        // Check user is set
        if (!current_user_can('manage_options')) {
            \WP_CLI::error("You must be logged in as a user with the 'manage_options' capability to run this command. Remember to include the --user argument when running the command, e.g. --user=admin");
            return;
        }

        // Parse arguments
        $installableHandle = $args[0] ?? null;
        $refresh           = isset($assoc_args['refresh']) && $assoc_args['refresh'] === true ? true : false;

        // Prep the install action
        $installable = $this->prep('install', Theme::instance(), $installableHandle, $refresh);

        // Run the install action
        if ($installable !== false) {
            $this->runAction('install', $installable, $refresh);
        }
    }
    
    /**
     * Installs a package's migrations, or a specific migration for the package, with the option to refresh them.
     *
     * ## OPTIONS
     *
     * <package>
     * : The package handle to run migrations for.
     * [<installable>]
     * : The handle of the migration to run. If not provided, all migrations for the package will be run.
     * 
     * [--refresh]
     * : Whether to rollback migrations before running them again. (default: false)
     *
     * ## EXAMPLES
     *
     * wp meros:install package my_package_handle --user=admin
     * wp meros:install package my_package_handle my_migration_slug --user=admin
     * wp meros:install package my_package_handle my_migration_slug --refresh --user=admin
     *
     * @subcommand package
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function package($args, $assoc_args) {
        // Check user is set
        if (!current_user_can('manage_options')) {
            \WP_CLI::error("You must be logged in as a user with the 'manage_options' capability to run this command. Remember to include the --user argument when running the command, e.g. --user=admin");
            return;
        }
        
        // Parse arguments
        $packageHandle     = $args[0] ?? null;
        $installableHandle = $args[1] ?? null;
        $refresh           = isset($assoc_args['refresh']) && $assoc_args['refresh'] === true ? true : false;

        // Get the package to run on
        $package = Registry::getPackages()->firstWhere('handle', $packageHandle);
        if (!$package) {
            \WP_CLI::error("No package found with handle: " . $packageHandle);
            return;
        }

        // Prep the install action
        $installable = $this->prep('install', $package, $installableHandle, $refresh);
        
        // Run the install action
        if ($installable !== false) {
            $this->runAction('install', $installable, $refresh);
        }
    }
}