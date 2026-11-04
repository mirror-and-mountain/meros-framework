<?php

namespace MM\Meros\Scripts;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Theme;
use MM\Meros\Facades\Packages;

use MM\Meros\App\Package;

class UpdateCommands {
    /**
     * Updates the framework tables.
     *
     * ## OPTIONS
     * 
     * [--table-name=<table>]
     * : The name of the table to update.
     *
     * ## EXAMPLES
     *
     * wp meros:update framework
     * wp meros:update framework --table-name=<table>
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
        } else {
            $tableName = null;
        }

        \WP_CLI::line('Updating framework tables' . ($tableName ? ' (' . $tableName . ')' : ''));

        try {
            Framework::update($tableName);
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }

        \WP_CLI::success('Framework operation completed successfully.');

    }

    /**
     * Updates the theme tables.
     *
     * ## OPTIONS
     * 
     * [--table-name=<table>]
     * : The name of the table to update.
     *
     * ## EXAMPLES
     *
     * wp meros:update theme
     * wp meros:update theme --table-name=<table>
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
        } else {
            $tableName = null;
        }

        \WP_CLI::line('Updating theme tables' . ($tableName ? ' (' . $tableName . ')' : ''));

        try {
            Theme::update($tableName);
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }

        \WP_CLI::success('Theme operation completed successfully.');
    }

    /**
     * Updates a package's tables.
     *
     * ## OPTIONS
     * 
     * <package>
     * : The package to update tables for.
     * 
     * [--table-name=<table>]
     * : The name of the table to update.
     *
     * ## EXAMPLES
     *
     * wp meros:update package my-package
     * wp meros:update package my-package --table-name=<table>
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
        } else {
            $tableName = null;
        }

        if (empty($args[0])) {
            \WP_CLI::error('Please specify a package.');
        }

        $packageName = $args[0];
        $package = Packages::get($packageName);

        if ($package instanceof Package === false) {
            \WP_CLI::error('Package not found.');
        }

        \WP_CLI::line('Updating package tables for ' . $packageName . ($tableName ? ' (' . $tableName . ')' : ''));

        try {
            $package->update($tableName);
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }

        \WP_CLI::success('Package operation completed successfully.');
    }
}