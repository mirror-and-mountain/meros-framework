<?php

namespace MM\Meros\Scripts;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;

class EnvironmentCommands {
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
     * wp meros:env connect production
     *
     * @subcommand connect
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
     * Syncs two environments.
     *
     * ## OPTIONS
     *
     * <from>
     * : The environment to sync the theme from.
     * <to>
     * : The environment to sync the theme to.
     * 
     * [--drop-table]
     * : Whether to add a DROP TABLE statement before each CREATE TABLE statement in the generated SQL. (default: false)
     * 
     * [--search-replace]
     * : Whether to perform a search and replace operation on the database. (default: true)
     *
     * [--activate-plugins]
     * : Whether to activate the plugins after syncing. (default: true)
     *
     *
     * ## EXAMPLES
     *
     * wp meros:env sync local development
     * wp meros:env sync staging production
     *
     * @subcommand sync
     *
     * @when after_wp_load
     *
     * @param  array  $args  Positional arguments.
     * @param  array  $assoc_args  Associative arguments.
     */
    public function sync($args, $assoc_args) {
        // Parse arguments
        $from = $args[0];
        $to   = $args[1];

        // Determine flags
        $addDropTable    = isset($assoc_args['drop-table']) && $assoc_args['drop-table'] === true ? true : false;
        $activatePlugins = isset($assoc_args['activate-plugins']) && $assoc_args['activate-plugins'] === false ? false : true;
        $searchReplace   = isset($assoc_args['search-replace']) && $assoc_args['search-replace'] === false ? false : true;

        // Get environments config
        $environmentsConfig = Config::get('environments');

        // Validate environments
        $isLocal = $from === 'local' || $to === 'local';
        if (! isset($environmentsConfig['remote_environments'][$from]) && ! $isLocal) {
            \WP_CLI::error(sprintf('Source environment "%s" is not defined in the configuration.', $from));
            return;
        }

        if (! isset($environmentsConfig['remote_environments'][$to]) && ! $isLocal) {
            \WP_CLI::error(sprintf('Destination environment "%s" is not defined in the configuration.', $to));
            return;
        }

        // Set the environments
        $sourceConfig = $isLocal && $from === 'local' ? 'local' : $environmentsConfig['remote_environments'][$from];
        $destConfig   = $isLocal && $to === 'local' ? 'local' : $environmentsConfig['remote_environments'][$to];

        // Determine the sync template key
        $templateKey = sprintf('%s_to_%s', $from, $to);
        $syncConfig  = $environmentsConfig['sync_templates'][ $templateKey ] ?? $environmentsConfig['sync_templates']['default'] ?? null;

        if ($syncConfig === null) {
            \WP_CLI::error('No sync configuration found for the specified environments, and no default configuration is set.');
            return;
        }

        // Determine the tables to sync
        $tablesToSync = array_keys(Arr::where($syncConfig['tables'] ?? [], function ($value) {
            return $value !== false;
        }));

        // Determine the plugins to sync (if any)
        $syncPlugins   = isset($syncConfig['plugins']) && (is_array($syncConfig['plugins']) || $syncConfig['plugins'] === true) ? true : false;
        $pluginsToSync = null;

        if ($syncPlugins === true) {
            if (is_array($syncConfig['plugins'])) {
                $pluginsToSync = array_keys(Arr::where($syncConfig['plugins'], function ($value) {
                    return $value !== false;
                }));
            } else {
                $pluginsToSync = 'all';
            }
        }

        // Determine whether to sync uploads
        $syncUploads = isset($syncConfig['uploads']) && $syncConfig['uploads'] === true ? true : false;

        // Determine whether to sync options
        $optionsToSync = $syncConfig['options'] ?? [];

        // Determine options to maintain on the destination environment
        $optionsToMaintain = $destConfig['options'] ?? [];

        // Confirm action
        \WP_CLI::warning(sprintf('You are about to perform a synchronisation between the "%s" environment and the "%s" environment with the following configuration:', $from, $to));
        
        \WP_CLI::line('Tables to sync: ' . (empty($tablesToSync) ? 'None' : implode(', ', $tablesToSync)));
        \WP_CLI::line('Plugins to sync: ' . ($syncPlugins === true ? ($pluginsToSync === 'all' ? 'All' : implode(', ', $pluginsToSync)) : 'None'));
        \WP_CLI::line('Uploads to sync: ' . ($syncUploads ? 'Yes' : 'No'));
        \WP_CLI::line('Options to sync: ' . (empty($optionsToSync) ? 'None' : implode(', ', $optionsToSync)));
        \WP_CLI::line('Options to maintain on destination: ' . (empty($optionsToMaintain) ? 'None' : implode(', ', array_keys($optionsToMaintain))));
        \WP_CLI::line('Add DROP TABLE statements: ' . ($addDropTable ? 'Yes' : 'No'));
        \WP_CLI::line('Perform search and replace: ' . ($searchReplace ? 'Yes' : 'No'));
        \WP_CLI::line('Activate plugins after sync: ' . ($activatePlugins ? 'Yes' : 'No'));
        
        \WP_CLI::line('It is highly recommended to back up your theme files and database before proceeding. Note that this operation will also synchronise the theme files from the source environment to the destination environment, which may result in changes to the destination environment\'s theme. If you have made customisations to the destination environment\'s theme, please ensure you have a backup before proceeding.');
        
        \WP_CLI::confirm('Are you sure you want to proceed?');

        // Get Environment Manager
        $manager = EnvironmentManager::get($from);

        // Sync theme
        $themeResult = $manager->syncTheme($to);
        if ($themeResult === false) {
            $error = $manager->getError();
            \WP_CLI::error($error);
            return;
        } else {
            \WP_CLI::success(sprintf('Theme sync from "%s" to "%s" completed successfully.', $from, $to));
        }

        // Sync database
        $dbResult = $manager->syncDatabase($to, $tablesToSync, $optionsToSync, $optionsToMaintain, $addDropTable, $searchReplace);
        if ($dbResult === false) {
            $error = $manager->getError();
            \WP_CLI::error($error);
            return;
        } else {
            \WP_CLI::success(sprintf('Database sync from "%s" to "%s" completed successfully.', $from, $to));
        }

        // Sync plugins
        if ($syncPlugins && $pluginsToSync !== null) {
            $pluginsResult = $manager->syncPlugins($to, $pluginsToSync, $activatePlugins);
            if ($pluginsResult === false) {
                $error = $manager->getError();
                \WP_CLI::error($error);
                return;
            } else {
                \WP_CLI::success(sprintf('Plugins sync from "%s" to "%s" completed successfully.', $from, $to));
            }
        }

        // Sync uploads
        if ($syncUploads) {
            $uploadsResult = $manager->syncUploads($to);
            if ($uploadsResult === false) {
                $error = $manager->getError();
                \WP_CLI::error($error);
                return;
            } else {
                \WP_CLI::success(sprintf('Uploads sync from "%s" to "%s" completed successfully.', $from, $to));
            }
        }

        \WP_CLI::success('Environment synchronisation completed successfully.');
    }
}
