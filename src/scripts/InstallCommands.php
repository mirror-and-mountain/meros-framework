<?php

namespace MM\Meros\Scripts;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Theme;
use MM\Meros\Facades\Packages;

use MM\Meros\App\Package;

class InstallCommands {
    /**
     * Installs the framework tables.
     *
     * ## OPTIONS
     * 
     * [--table-name=<table>]
     * : The name of the table to install.
     *
     * [--required-only]
     * : Only install required tables.
     *
     * ## EXAMPLES
     *
     * wp meros:install framework
     * wp meros:install framework --required-only
     * wp meros:install framework --table-name=<table>
     *
     * @subcommand framework
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function framework($args, $assoc_args) {
        if (isset($assoc_args['table-name'])) {
            $tableName = $assoc_args['table-name'];
            $requiredOnly = false;
        } else {
            $tableName = null;
            $requiredOnly = isset($assoc_args['required-only']) && $assoc_args['required-only'] === true;
        }

        if ($tableName !== null) {
            \WP_CLI::line('Installing framework table ' . $tableName);
        } else {
            \WP_CLI::line('Installing all framework tables' . ($requiredOnly ? ' (required only)' : ''));
        }

        try {
            Framework::install($tableName, $requiredOnly);
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }

        \WP_CLI::success('Framework operation completed successfully.');

    }

    /**
     * Installs the theme tables.
     *
     * ## OPTIONS
     * 
     * [--table-name=<table>]
     * : The name of the table to install.
     *
     * [--required-only]
     * : Only install required tables.
     *
     * ## EXAMPLES
     *
     * wp meros:install theme
     * wp meros:install theme --required-only
     * wp meros:install theme --table-name=<table>
     *
     * @subcommand theme
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function theme($args, $assoc_args) {
        if (isset($assoc_args['table-name'])) {
            $tableName = $assoc_args['table-name'];
            $requiredOnly = false;
        } else {
            $tableName = null;
            $requiredOnly = isset($assoc_args['required-only']) && $assoc_args['required-only'] === true;
        }

        if ($tableName !== null) {
            \WP_CLI::line('Installing theme table ' . $tableName);
        } else {
            \WP_CLI::line('Installing all theme tables' . ($requiredOnly ? ' (required only)' : ''));
        }

        try {
            Theme::install($tableName, $requiredOnly);
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }

        \WP_CLI::success('Theme operation completed successfully.');
    }

    /**
     * Installs a package's tables.
     *
     * ## OPTIONS
     * 
     * <package>
     * : The package to install tables for.
     * 
     * [--table-name=<table>]
     * : The name of the table to install.
     *
     * [--required-only]
     * : Only install required tables.
     *
     * ## EXAMPLES
     *
     * wp meros:install package my-package
     * wp meros:install package my-package --required-only
     * wp meros:install package my-package --table-name=<table>
     *
     * @subcommand package
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function package($args, $assoc_args) {
        if (isset($assoc_args['table-name'])) {
            $tableName = $assoc_args['table-name'];
            $requiredOnly = false;
        } else {
            $tableName = null;
            $requiredOnly = isset($assoc_args['required-only']) && $assoc_args['required-only'] === true;
        }

        if (empty($args[0])) {
            \WP_CLI::error('Please specify a package.');
        }

        $packageName = $args[0];
        $package = Packages::get($packageName);

        if ($package instanceof Package === false) {
            \WP_CLI::error('Package not found.');
        }

        if ($tableName !== null) {
            \WP_CLI::line('Installing package table ' . $tableName . ' for ' . $packageName);
        } else {
            \WP_CLI::line('Installing all package tables for ' . $packageName . ($requiredOnly ? ' (required only)' : ''));
        }

        try {
            $package->install($tableName, $requiredOnly);
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }

        \WP_CLI::success('Package operation completed successfully.');
    }
}