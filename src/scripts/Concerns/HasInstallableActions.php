<?php 

namespace MM\Meros\Scripts\Concerns;

use MM\Meros\App\Theme as ThemeInstance;
use MM\Meros\App\Package;
use MM\Meros\App\Features\Installable;

use MM\Meros\App\Facades\Registry;

trait HasInstallableActions {
    /**
     * Preps the install or uninstall action and returns the installable or item to run the action for, or false if the action was cancelled or an error occurred.
     *
     * @param  string                $action
     * @param  ThemeInstance|Package $item
     * @param  string|null           $installableHandle
     * @param  bool                  $refresh
     *
     * @return false|ThemeInstance|Package|Installable
     */
    private function prep(string $action, ThemeInstance|Package $item, string|null $installableHandle = null, $refresh = false): false|ThemeInstance|Package|Installable {

        // Get the installable to run if specified
        $installable = null;
        if ($installableHandle) {
            $installable = Registry::getInstallables()->where('source', $item)->firstWhere('handle', $installableHandle);
        }

        if ($installableHandle && !$installable) {
            \WP_CLI::error("No installable found with handle: " . $installableHandle);
            return false;
        }

        // Confirm the action with the user before proceeding
        $confirmationMessage = "Are you sure you want to " . $action . " " . ($installable ? "'" . $installable->handle . "'" : ($item instanceof ThemeInstance ? "the theme's installables" : "'" . $item->handle . "'s' installables")) . ($action === 'install' ? ($refresh ? " with refresh" : "") : "") . "? This action cannot be undone.";
        
        \WP_CLI::confirm($confirmationMessage);

        return $installable ?? $item;
    }

    /**
     * Run an install or uninstall action for a given item and installable, with optional refresh.
     *
     * @param string                            $action The action to perform: 'install' or 'uninstall'.
     * @param ThemeInstance|Package|Installable $item The item to run the action for. This can be a theme instance, a package instance, or an individual installable.
     * @param bool                              $refresh Whether to refresh the item by uninstalling before installing again (only applicable for 'install' action).
     * 
     * @return void
     */
    private function runAction(string $action, ThemeInstance|Package|Installable $item, $refresh) {
        $isIndividualInstallable = $item instanceof Installable;

        if ($action === 'install') {
            if ($refresh) {
                // Rollback the item's installables before running them again
                $uninstallResult = $item->uninstall();

                if ($uninstallResult !== true) {
                    \WP_CLI::error("Failed to refresh item: " . ($isIndividualInstallable ? $item->uninstallationError : $uninstallResult));
                    return;
                }

                // Run the item's installables again
                $installResult = $item->install();
                if ($installResult !== true) {
                    \WP_CLI::error("Failed to refresh item: " . ($isIndividualInstallable ? $item->installationError : $installResult));
                    return;
                }
            }

            else {
                $installResult = $item->install();
                if ($installResult !== true) {
                    \WP_CLI::error("Failed to install item: " . ($isIndividualInstallable ? $item->installationError : $installResult));
                    return;
                }
            }

            \WP_CLI::success("The item was installed successfully: " . $item->handle);
        }

        else if ($action === 'uninstall') {
            $uninstallResult = $item->uninstall();

            if ($uninstallResult !== true) {
                \WP_CLI::error("Failed to uninstall item: " . ($isIndividualInstallable ? $item->uninstallationError : $uninstallResult));
                return;
            }

            \WP_CLI::success("The item was uninstalled successfully: " . $item->handle);
        }
    }
}