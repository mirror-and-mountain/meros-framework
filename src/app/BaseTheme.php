<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;
use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\Setting;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Contracts\Providers\Concerns\IsNonFrameworkProvider;
use MM\Meros\Contracts\Providers\Concerns\IsNonPackageProvider;

use MM\Meros\Contracts\Features\Data\TableCreator;
use MM\Meros\Support\SchemaManager;

use MM\Meros\App\Models\Migration as TrackedMigration;
use MM\Meros\App\Models\Option;

abstract class BaseTheme extends Provider {
    use IsNonFrameworkProvider, IsNonPackageProvider;

    // =========================================================================
    // Initialisation
    // =========================================================================
    
    final protected function init(): void {
        $themeInfo = \wp_get_theme();

        $this->setName($themeInfo->get('Name'));
        $this->setDescription($themeInfo->get('Description'));
        $this->setAuthor($themeInfo->get('Author'));
        $this->setAuthorUrl($themeInfo->get('AuthorURI'));
        $this->setPath(\get_stylesheet_directory());
        $this->setUri(\get_stylesheet_directory_uri());

        $this->enqueueStyleSheet();
    }

    /**
     * Used here to configure theme settings provided by the framework in addition to any settings already registered by the theme.
     * These settings include options that relate to the theme's behavior on deactivation, such as whether to uninstall all custom tables or reset all settings.
     *
     * @return void
     */
    final public function whenConfigured(): void {
        add_filter('meros_settings_field_title', function (string $title, string $id, Setting $setting) {
            $name = $setting->getName();
            if ($name !== 'meros_uninstall_all_tables_on_deactivate' && $name !== 'meros_reset_settings_on_deactivate') {
                return $title;
            }

            $message = $name === 'meros_uninstall_all_tables_on_deactivate' 
                ? 'uninstall all custom tables registered by the meros framework, the theme, and any meros packages' 
                : 'reset all meros settings stored in the database, including settings for the theme and any meros packages';

            return 
                '<div class="meros-settings-field-title-wrapper">
                    <label for="' . esc_attr($id) . '">' . $setting->getLabel() . '</label>
                    <div class="meros-settings-field-description">
                        <span class="description">
                            Toggling this setting on will ' . $message . ' when the theme is deactivated. This action will likely lead to data loss, so use with caution and always make sure your site is backed-up.
                        </span>
                    </div>
                </div>';
        }, 10, 3);

        $this->settings()->add('boolean', function ($setting) {
            $setting->name('meros_reset_settings_on_deactivate');
            $setting->label('Reset All Settings On Theme Deactivation');
            $setting->default(false);
            $setting->field();
        });

        $this->settings()->add('boolean', function ($setting) {
            $setting->name('meros_uninstall_all_tables_on_deactivate');
            $setting->label('Reset All Customisations On Theme Deactivation');
            $setting->default(false);
            $setting->field();
        });

        // Fires when the theme is activated, triggering any necessary setup actions.
        add_action('after_switch_theme', function () {
            $this->__whenThemeActivated();
        });

        // Fires when the theme is deactivated, triggering any necessary cleanup actions.
        add_action('switch_theme', function () {
            $this->__whenThemeDeactivated();
        });
    }

    /**
     * Fires when the theme is deactivated, triggering any necessary cleanup actions.
     *
     * @return void
     */
    final protected function whenThemeDeactivated(): void {
        $removeAllTablesOnDeactivate = $this->getConfigurationValue('meros_uninstall_all_tables_on_deactivate', true);
        $resetAllSettingsOnDeactivate = $this->getConfigurationValue('meros_reset_settings_on_deactivate');

        if ($removeAllTablesOnDeactivate) {
            $this->uninstallAllMerosTables();
        }

        if ($resetAllSettingsOnDeactivate) {
            $this->removeAllMerosSettings();
        }
    }

    /**
     * Removes all Meros settings from the database.
     *
     * @return void
     */
    private function removeAllMerosSettings(): void {
        Option::where('option_name', 'LIKE', 'meros_%')->delete();
    }

    /**
     * Uninstalls all Meros tables from the database.
     *
     * @return void
     */
    private function uninstallAllMerosTables(): void {
        $migrationTableInstalled = SchemaManager::trackingTableExists();

        if ($migrationTableInstalled) {
            $migrations = TrackedMigration::where('type', 'create')->orderBy('id', 'desc')->get();

            $migrations->each(function (TrackedMigration $trackedMigration) {
                $path = $trackedMigration->path;
                if (!Str::startsWith($path, $this->getPath())) {
                    $path = trailingslashit($this->getPath()) . $trackedMigration->path;
                }

                $migration = include($path);

                if ($migration instanceof TableCreator) {
                    $migration->__init();
                    $migration->down($trackedMigration->related_table);
                }
            });
        }
    }

    /**
     * Enqueues the theme's stylesheet
     * 
     * @return void
     */
    private function enqueueStyleSheet(): void {
        $handle  = $this->getHandle() . '_style'; // e.g. meros_style.
        $uri     = get_stylesheet_uri();
        $version = filemtime(trailingslashit(get_stylesheet_directory()) . 'style.css');

        add_action('wp_enqueue_scripts', function () use ($handle, $uri, $version) {
            wp_enqueue_style(
                $handle,
                $uri,
                [],
                $version
            );
        });

        add_action('enqueue_block_editor_assets', function () use ($handle, $uri, $version) {
            wp_enqueue_style(
                $handle,
                $uri,
                [],
                $version
            );
        });
    }

    // =========================================================================
    // Settings Management
    // =========================================================================
    /**
     * Resolves the settings container for the theme.
     *
     * @param SettingsContainers $register The SettingsContainers register.
     *
     * @return SettingsContainer The settings container for the theme.
     */
    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        $container = $register->get('meros_theme_settings', null, false) ?? 
            $register->makeFrom('meros_theme_settings');

        $hasRegisteredTables = $this->hasRegisteredTables();

        if ($hasRegisteredTables) {
            $themeSettingsPage = $this->menuPages()->get('meros-theme-settings');

            if ($themeSettingsPage === null) {
                $themeSettingsPage = $this->menuPages()->makeFrom('meros-theme-settings');
            }

            $this->initTableManagementPage($themeSettingsPage);
        }

        return $container;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Adds a Wordpress theme support.
     * 
     * @param  string $support
     * @param  mixed  ...$args
     * 
     * @return void
     */
    final protected function addThemeSupport(string $support, mixed ...$args): void {
        add_theme_support($support, $args);
    }
}
