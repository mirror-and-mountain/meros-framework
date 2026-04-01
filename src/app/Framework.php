<?php

namespace MM\Meros\App;

use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Models\Migration;

use MM\Meros\App\Facades\Registry;
use MM\Meros\App\Facades\Context;
use MM\Meros\App\Facades\Theme;

final class Framework extends FeatureProvider {
    protected bool $discoverInstallables = true;
    protected bool $discoverSettings = true;
    protected bool $discoverAssets = true;

    protected function initialise(): void {
        // Do nothing...
    }

    /**
     * Intialises the Meros framework.
     * 
     * @return void
     */
    public function initialiseFramework(): void {   
        $currentPage = Context::currentPage();
        $initAjax    = Context::isAdmin();

        if ($initAjax) {
            $this->initAdminAjaxHandlers();
        }    

        $this->discoverInstallables();
        $this->discoverSettings();
        $this->discoverAssets();
    }

    /**
     * Returns whether the given framework service has been installed.
     * 
     * @param boolean $tryToInstall Whether to attempt to install the service if it hasn't been installed.
     *
     * @return bool Returns true if the service is installed, false if it isn't or if installation fails.
     */
    public function isServiceInstalled(string $service, bool $tryToInstall = false): bool {
        if ($service === 'core') {
            return $this->isCoreInstalled($tryToInstall);
        }

        if ($service === 'integrations') {
            return $this->isIntegrationsInstalled($tryToInstall);
        }

        return false; // Service not recognised
    }

    /**
     * Returns whether the core framework is installed.
     *
     * @param  boolean $tryToInstall Whether to attempt to install the core service if it isn't installed.
     *
     * @return boolean Returns true if the core service is installed, false if it isn't or if installation fails.
     */
    private function isCoreInstalled(bool $tryToInstall): bool {
        $installed = false;

        if (! Schema::hasTable('meros_migrations') || ! Migration::where('handle', '001_create_meros_migrations_table')->exists()) {
            $installed = $tryToInstall ? $this->installFramework() : false;
        } else {
            $installed = true;
        }

        if ($installed) {
            $coreMigrationRecord = Migration::where(
                'handle', 'like', '%create_meros_migrations_table%'
            )->first();

            $installed = $coreMigrationRecord !== null;
        } 
        
        return $installed;
    }

    /**
     * Returns whether the integrations service is installed.
     *
     * @param  boolean $tryToInstall Whether to attempt to install the integrations service if it isn't installed.
     *
     * @return boolean Returns true if the integrations service is installed, false if it isn't or if installation fails.
     */
    private function isIntegrationsInstalled(bool $tryToInstall): bool {
        $tables = [
            'meros_integration_accounts',
            'meros_integration_connections'
        ];

        $installed = true;

        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || 
                ! Migration::where('related_table', $table)->exists()
            ) {
                $installed = false;
                break;
            }
        }

        return $installed ? true : ($tryToInstall ? $this->installIntegrations() : false);
    }

    /**
     * Installs integration tables for the integrations service.
     *
     * @return boolean
     */
    private function installIntegrations(): bool {
        return $this->install() === true;
    }
 
    /**
     * Sets up AJAX handlers for the admin pages.
     *
     * @return void
     */
    private function initAdminAjaxHandlers(): void {
        add_action('wp_ajax_meros_toggle_package', [$this, 'handlePackageToggle']);
        add_action('wp_ajax_meros_install_feature', [$this, 'handleInstaller']);
        add_action('wp_ajax_meros_update_feature', [$this, 'handleInstaller']);
        add_action('wp_ajax_meros_uninstall_feature', [$this, 'handleInstaller']);
    }

    /**
     * Handles toggling packages on and off from the features page.
     *
     * @return void
     */
    public function handlePackageToggle(): void {
        $package = sanitize_key($_POST['package'] ?? '');
        $nonce   = $_POST['nonce'] ?? '';
        $action  = 'meros_toggle_package_' . $package;

        if (! $package || ! wp_verify_nonce($nonce, $action)) {
            wp_send_json_error([
                'message' => 'Invalid request'
            ]);
        }

        $packageItem = Registry::getPackage($package);
        
        if ($packageItem === null) {
            wp_send_json_error([
                'message' => 'Package not found'
            ]);
        }

        $isEnabledByDefault = $packageItem->getPreference('is_enabled_by_default');

        $option   = $package . '_enable';
        $current  = (bool) get_option($option, $isEnabledByDefault);
        $updated  = update_option($option, $current ? false : true);

        if (! $updated) {
            wp_send_json_error('Failed to update package status');
        }

        wp_send_json_success([
            'value' => (int) ! $current,
            'nonce' => wp_create_nonce($action)
        ]);
    }

    /**
     * Handles installing, updating and uninstalling packages and theme installables.
     *
     * @return void
     */
    public function handleInstaller(): void {
        $action = sanitize_key($_POST['action'] ?? '');
        $item   = sanitize_key($_POST['installable'] ?? '');
        $nonce  = $_POST['nonce'] ?? '';

        if (! $action || ! $item || ! wp_verify_nonce($nonce, $action . '_' . ($item !== 'theme' ? $item : 'theme'))) {
            wp_send_json_error([
                'message' => 'Invalid request'
            ]);
        }

        $installable = $item !== 'theme' 
            ? Registry::getPackage($item)
            : 'theme';

        if ($installable === null) {
            wp_send_json_error([
                'message' => 'Item not found'
            ]);
        }

        $result = $installable === 'theme' 
            ? Theme::install()
            : $installable->install();

        if ($result !== true) {
            wp_send_json_error([
                'message' => $result
            ]);
        }

        wp_send_json_success();
    }
}