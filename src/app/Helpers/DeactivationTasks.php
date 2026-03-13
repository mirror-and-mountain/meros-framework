<?php 

namespace MM\Meros\App\Helpers;

use MM\Meros\App\Services\Theme\ThemeManager;

class DeactivationTasks {
    /**
     * Unregisters settings registered via the theme manager or in packages and
     * deletes their corresponding options from the database.
     * 
     * @param ThemeManager $themeInstance The theme manager instance.
     * @return void
     */
    public static function removeSettings(ThemeManager $theme): void {
        $settings = $theme->getRegisteredSettings();
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
     * @param ThemeManager $theme The theme manager instance.
     * @return void
     */
    public static function reverseCoreMigrations(ThemeManager $theme): void {
        if ($theme->allowsMigrations() !== false) {
            $theme->setMerosCoreMigrations();
            $theme->rollbackMigrations();
        }
    }
}