<?php

namespace MM\Meros\App;

use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Models\Migration;
use MM\Meros\App\Features\CoreInstallable;

use MM\Meros\App\Facades\Registry;
use MM\Meros\App\Facades\Context;
use MM\Meros\App\Facades\Theme;

final class Framework extends FeatureProvider {

    protected function configure(): void {
        // Run theme activation tasks
        add_action('after_switch_theme', [$this, 'runActivationTasks']);

        // Set migrations path preference
        $this->setPreference('migrations_path', 'database/migrations/integrations');
    }

    /**
     * Called from the FrameworkServiceProvider on boot
     *
     * @return self
     */
    public function __initialise(): self {
        $this->initialise();
        return $this;
    }

    /**
     * Initialises the framework's features
     *
     * @return void
     */
    protected function initialise(): void {
        $this->registerRestRoutes();
        $this->discover()->assets();
        $this->discover()->blocks(); 

        // $initAjax = Context::isAdmin();

        // if ($initAjax) {
        //     $this->initAdminAjaxHandlers();
        // }    

        // $this->discoverInstallables();
        // $this->discoverAssets();
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

        if (
            ! Schema::hasTable('meros_migrations') || 
            ! Migration::where('handle', '001_create_meros_migrations_table')->exists()
        ) {
            $installed = $tryToInstall ? $this->installFramework() : false;
        } else {
            $installed = true;
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
        $installed = $this->isInstalled();

        return $installed ? true : ($tryToInstall ? $this->install() : false);
    }

    /**
     * Returns whether the framework is installed.
     * Only includes the Integration service tables as the 'core' migrations table is handled separately.
     *
     * @return boolean
     */
    protected function isInstalled(): bool {
        $tables = [
            'meros_integration_accounts',
            'meros_integration_connections'
        ];

        return $this->hasTables($tables);
    }

    /**
     * Installs the core Meros migrations table so that other feature providers
     * can run installables.
     *
     * @return bool Returns true on success, false on failure.
     */
    private function installFramework(): bool {
        if (!$this instanceof Framework) {
            return false;
        }

        $migrationPath = \trailingslashit(
            \trailingslashit($this->getPreference('migrations_path')) . 'core' . DIRECTORY_SEPARATOR
        );

        $installable = $this->makeCoreInstallable([
            'path'   => $migrationPath . '001_create_meros_migrations_table.php',
        ]);

        $installed = $installable->install();

        if ($installed !== true) {
            return false;
        }

        return true;
    }

    /**
     * Creates the core installable instance for the Meros framework.
     *
     * @param array $config The configuration array for the core installable.
     * 
     * @return CoreInstallable|null The created core installable instance, or null if it cannot be created.
     */
    private function makeCoreInstallable(array $config): CoreInstallable|null {
        if (! $this instanceof Framework) {
            return null;
        }

        return app(
            CoreInstallable::class, [
                'source' => $this,
            ]
        )->make($config);
    }

    /***************************************************************
     * 
     * The following methods are for Meros API endpoints
     * 
     ***************************************************************/
    /**
     * Registers REST API routes for the framework.
     *
     * @return void
     */
    private function registerRestRoutes(): void {
        /**
         * Registers a REST API route for rendering Blade views. Accepts a view name and an optional 
         * data payload, renders the specified view with the provided data, and returns the rendered HTML. 
         * 
         * Requires the 'edit_posts' capability to access.
         */
        add_action('rest_api_init', function () {
            register_rest_route('meros/v1', '/get-blade-view', [
                'methods'             => \WP_REST_Server::READABLE,
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'callback' => function (\WP_REST_Request $request) {
                    $view = sanitize_text_field($request->get_param('view'));
                    $data = $request->get_param('data');
                    $attributes = [];

                    if (! $view) {
                        return new \WP_Error('no_view', 'No view specified', ['status' => 400]);
                    }

                    if (is_string($data) && $data !== '') {
                        $decoded = json_decode(wp_unslash($data), true);

                        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                            return new \WP_Error('invalid_data', 'Invalid data payload', ['status' => 400]);
                        }

                        $attributes = $decoded;
                    }

                    try {
                        $rendered = view($view, [
                            'attributes' => $attributes,
                            'data'       => $attributes,
                        ])->render();
                        return rest_ensure_response(['html' => $rendered]);
                    } catch (\Exception $e) {
                        return new \WP_Error('render_error', 'Error rendering view: ' . $e->getMessage(), ['status' => 500]);
                    }
                }
            ]);

            /** Serve this endpoint as raw HTML so block editor fetch().text() receives renderable markup. */
            add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
                if ($request->get_route() !== '/meros/v1/get-blade-view') {
                    return $served;
                }

                if (is_wp_error($result)) {
                    return $served;
                }

                $data = $result instanceof \WP_REST_Response ? $result->get_data() : null;

                if (! is_array($data) || ! isset($data['html'])) {
                    return $served;
                }

                $server->send_header('Content-Type', 'text/html; charset=' . get_option('blog_charset'));
                echo $data['html'];

                return true;
            }, 10, 4);
        });
    }

    /***************************************************************
     * 
     * The following methods are used for AJAX calls from WP Admin
     * 
     ***************************************************************/
 
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

    /**
     * Gets the instance of the framework.
     *
     * @return Framework
     */
    final public function instance(): Framework {
        return $this;
    }

    /*************************************************************
     * 
     * The following methods are called on theme activation....
     * 
     *************************************************************/

    public function runActivationTasks(): void {
        $this->ensureAppKey();
        $this->ensurePrettyPermalinks();
        $this->clearSessionFiles();
    }

    /**
     * Ensures that an APP_KEY exists in the theme's .env file.
     *
     * @return void
     */
    private function ensureAppKey(): void {
        $envPath = base_path('.env');
        $key     = 'base64:' . base64_encode(random_bytes(32));
        $comment = "# An App Key is required for some Meros functionality. It is automatically generated on theme activation.";

        if (!file_exists($envPath)) {
            $envContent = "{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($envPath, $envContent);
            return;
        }

        $envContent = file_get_contents($envPath);

        if (!preg_match('/^APP_KEY=.*$/m', $envContent)) {
            $envContent = rtrim($envContent) . "\n\n{$comment}\nAPP_KEY={$key}\n";
            file_put_contents($envPath, $envContent);
        }
    }

    /**
     * Ensures that pretty permalinks are enabled.
     * 
     * @return void
     */
    private function ensurePrettyPermalinks(): void {
        global $wp_rewrite;
        $permalinkStructure = get_option('permalink_structure');
        if (empty($permalinkStructure) || $permalinkStructure === '/') {
            $wp_rewrite->set_permalink_structure('/%postname%/');
            $wp_rewrite->flush_rules();
        }
    }

    /**
     * Clears session files from the theme's storage directory.
     * 
     * @return void
     */
    private function clearSessionFiles(): void {
        $sessionDir = get_theme_file_path('storage/framework/sessions');

        if (is_dir($sessionDir)) {
            $files = glob($sessionDir . '/*');

            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}