<?php 

namespace MM\Meros\App\Helpers;

use MM\Meros\App\Facades\Theme;
use MM\Meros\App\Facades\Admin;

class DeactivationTasks {
    /**
     * Unregisters settings registered via the theme manager or in packages and
     * deletes their corresponding options from the database.
     * 
     * @return void
     */
    public static function removeSettings(): void {
        $settings = Theme::getRegisteredSettings();
        foreach ($settings as $_ => $optionGroups) {
            foreach ($optionGroups as $optionGroup => $options) {
                foreach ($options as $optionName => $_) {
                    unregister_setting($optionGroup, $optionName);
                    delete_option($optionName);
                }
            }
        }
    }

    /**
     * Clears session files from the theme's storage directory.
     * 
     * @return void
     */
    public static function clearSessionFiles(): void {
        ActivationTasks::clearSessionFiles();
    }

    /**
     * Rolls back core migrations if the theme allows it.
     * 
     * @return void
     */
    public static function reverseCoreMigrations(): void {
        Admin::setMerosCoreMigrations();
        Admin::rollbackMigrations();
    }
}