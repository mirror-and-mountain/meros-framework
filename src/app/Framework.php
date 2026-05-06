<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\App\Providers\FrameworkServiceProvider;

use MM\Meros\App\Fields\Checkbox;
use MM\Meros\App\Fields\Checkboxes;
use MM\Meros\App\Fields\Color;
use MM\Meros\App\Fields\Date;
use MM\Meros\App\Fields\MultiSelect;
use MM\Meros\App\Fields\Number;
use MM\Meros\App\Fields\Radio;
use MM\Meros\App\Fields\Repeater;
use MM\Meros\App\Fields\Select;
use MM\Meros\App\Fields\Text;
use MM\Meros\App\Fields\Textarea;
use MM\Meros\App\Fields\Time;
use MM\Meros\App\Fields\Url;

use MM\Meros\App\Fields\Styles\DefaultFieldStyle;
use MM\Meros\App\Fields\Styles\NiceFieldStyle;
use MM\Meros\App\Fields\Styles\SettingsFieldStyle;

use MM\Meros\App\Admin\SettingsSections\Blocks;
use MM\Meros\App\Admin\SettingsSections\Packages;

use MM\Meros\App\Admin\Templates\SimpleSettingsPage;
use MM\Meros\App\Admin\Templates\TabbedSettingsPage;
use MM\Meros\App\Admin\Templates\MerosFeaturesPage;

use MM\Meros\App\Theme as ThemeInstance;
use MM\Meros\Facades\Theme;
use MM\Meros\Facades\Packages as PackagesAccessor;

final class Framework extends FeatureProvider {
    /**
     * Called from the FrameworkServiceProvider on boot
     * 
     * @param FrameworkServiceProvider $serviceProvider Used to ensure only the FrameworkServiceProvider can call this method.
     *
     * @return self
     */
    public function __initialise(FrameworkServiceProvider $serviceProvider): self {
        $this->load();
        $this->configure();
        return $this;
    }

    /**
     * Initialises the framework's features
     *
     * @return void
     */
    protected function load(): void {
        // Register REST API routes
        $this->registerRestRoutes();

        // Register framework fields
        $this->fields()->register('checkbox', Checkbox::class);
        $this->fields()->register('checkboxes', Checkboxes::class);
        $this->fields()->register('color', Color::class);
        $this->fields()->register('date', Date::class);
        $this->fields()->register('multi_select', MultiSelect::class);
        $this->fields()->register('number', Number::class);
        $this->fields()->register('radio', Radio::class);
        $this->fields()->register('repeater', Repeater::class);
        $this->fields()->register('select', Select::class);
        $this->fields()->register('text', Text::class);
        $this->fields()->register('textarea', Textarea::class);
        $this->fields()->register('time', Time::class);
        $this->fields()->register('url', Url::class);

        // Register framework field styles
        $this->fieldStyles()->register('default', DefaultFieldStyle::class);
        $this->fieldStyles()->register('nice', NiceFieldStyle::class);

        // Only load admin features if we're in the admin context
        if ($this->context->isAdmin) {
            // Register the Settings field style for admin settings pages
            $this->fieldStyles()->register('settings', SettingsFieldStyle::class);

            // Register framework settings sections
            $this->settingsSections()->register('meros-features-packages', Packages::class);
            $this->settingsSections()->register('meros-features-blocks', Blocks::class);

            // Register menu page templates
            $this->menuPageTemplates()->register('simple-settings', SimpleSettingsPage::class);
            $this->menuPageTemplates()->register('tabbed-settings', TabbedSettingsPage::class);
            $this->menuPageTemplates()->register('meros-features', MerosFeaturesPage::class);

            // Initialise AJAX handlers for admin interactions
            $this->initAdminAjaxHandlers();
        }
    }

    /**
     * Configures the framework's features, settings and menu pages.
     *
     * @return void
     */
    protected function configure(): void {
        // Run theme activation tasks
        add_action('after_switch_theme', [$this, 'runActivationTasks']);

        // Configure settings and menu pages
        if ($this->context->isAdmin) {
            $this->configureSettings();
            $this->configureMenuPages();
        }
            

        // Discover assets
        $this->assets()->discover();

        // Discover blocks
        $this->blocks()->discover();
    }

    /*****************************************************************************************
     * 
     * The following methods are installing core framework tables when required by providers
     * 
     *****************************************************************************************/

    /**
     * Called by provider installers to ensure the framework's core tables are installed
     * before they undertake any installer operations.
     *
     * @return void
     */
    public function require(string $service = 'migrations'): void {
        if ($service === 'migrations') {
            $this->requireMigrationsService();
            return;
        }

        if ($service === 'integrations') {
            $this->requireIntegrationsService();
            return;
        }
    }

    /**
     * Installs the meros_migrations table if it doesn't exist.
     *
     * @return void
     * @throws \RuntimeException if the meros_migrations table cannot be found in the framework's tables collection.
     */
    private function requireMigrationsService() {
        $table = $this->tables()
            ->discover()
            ->checkout($this)
            ->all()
            ->where('tableName', 'meros_migrations')
            ->first();

        if ($table === null) {
            throw new \RuntimeException('Meros Framework requires the meros_migrations table to manage updates. No such table was found.');
        }

        if (!$table->isInstalled()) {
            $table->install(Str::ulid());
        }
    }

    /**
     * Installs the meros_integration_accounts and meros_integration_connections tables if they don't exist.
     *
     * @return void
     * @throws \RuntimeException if either the meros_integration_accounts or meros_integration_connections table cannot be found in the framework's tables collection.
     */
    private function requireIntegrationsService(): void {
        $this->requireMigrationsService(); // Ensure the migrations service is installed before attempting to install integration tables

        $tables = $this->tables()
            ->discover()
            ->checkout($this)
            ->all();

        $accountsTable = $tables
            ->where('tableName', 'meros_integration_accounts')
            ->first();

        $connectionsTable = $tables
            ->where('tableName', 'meros_integration_connections')
            ->first();

        if ($accountsTable === null || $connectionsTable === null) {
            throw new \RuntimeException('Meros Framework requires the meros_integration_accounts and meros_integration_connections tables to manage integrations. One or both of these tables were not found.');
        }

        $batchID = Str::ulid();

        if (!$accountsTable->isInstalled()) {
            $accountsTable->install($batchID);
        }

        if (!$connectionsTable->isInstalled()) {
            $connectionsTable->install($batchID);
        }
    }

    /***************************************************************
     * 
     * The following methods are for settings management
     * 
     ***************************************************************/
    /**
     * Sets up core settings provided by the framework.
     *
     * @return void
     */
    private function configureSettings(): void {
        // Clear installer operation query args when settings are saved.
        add_action('update_option_meros_framework_settings', function () {
            $url = $this->context->removeQueryArgs(['provider', 'operation']);
            $this->context->redirect($url);
        }, 10, 3);

        $packageSettings = $this->settings()->add(function ($setting) {
            $setting->object('packages')
                ->label('Packages');
        });

        $blockSettings = $this->settings()->add(function ($setting) {
            $setting->object('blocks')
                ->label('Blocks');

            $setting->add()->boolean('example_block_setting')
                ->label('Example Block Setting')
                ->description('This is an example setting for blocks.')
                ->field()
                    ->section('meros-features-blocks');
        });

        add_action('meros_providers_registered', function (ThemeInstance $theme, Collection $packages) use ($packageSettings) {
            foreach ($packages as $package) {
                $enabledSetting = $packageSettings->add()->boolean($package->getHandle() . '_enable')
                    ->label('Enable ' . $package->getName())
                    ->description($package->getDescription())
                    ->field()
                        ->section('meros-features-packages');

                $titleHTML = $this->getProviderSettingHTML($package);
                $enabledSetting->titleHTML($titleHTML);
            }
        }, 10, 2);
    }

    /**
     * Configures the framework's menu pages, including the main features page and any subpages.
     *
     * @return void
     */
    private function configureMenuPages(): void {
        $this->menuPages()->make(function ($page) {
            $page->slug('meros-features');
            $page->title('Features');
            $page->menuTitle('Features');
            $page->position(1);
            $page->template('meros-features', [
                'tabs'  => [
                    'theme' => [
                        'label'    => 'Theme',
                        'callback' => function () {
                            echo 'This is the theme tab';
                        }
                    ],
                    'packages' => [
                        'label'    => 'Packages',
                        'callback' => function () {
                            settings_fields('meros_framework_settings_group');
                            do_settings_sections('meros-features-packages');
                            submit_button();
                        }
                    ],
                    'blocks' => [
                        'label'    => 'Blocks',
                        'callback' => function () {
                            settings_fields('meros_framework_settings_group');
                            do_settings_sections('meros-features-blocks');
                            submit_button();
                        }
                    ],
                    'assets' => [
                        'label'    => 'Scripts & Styles',
                        'callback' => function () {
                            echo 'This is the assets tab';
                        }
                    ]
                ]
            ]);
        })->in('options');
    }

    /**
     * Generates the HTML for a provider's setting on the features page, including action links and status info.
     *
     * @param FeatureProvider $provider
     * @return string
     */
    private function getProviderSettingHTML(FeatureProvider $provider): string {
        $html        = '';
        $isPackage   = $provider instanceof Package;
        $isTheme     = !$isPackage;
        $enabled     = $isPackage ? $provider->isEnabled() : true; // Theme is always enabled as a provider
        $handle      = $provider->getHandle();
        $installed   = $provider->isInstalled();
        $installedAt = null;

        if ($enabled) {
            $html .=  
                '<div class="meros-provider-links>
                    <a href="' . $this->context->appendQueryArgs(['provider' => $provider->getHandle()]) .'">Settings</a>
                </div>';
        }

        $html .= '<div class="meros-provider-tasks"><div class="meros-installer-info">';

        if ($installed) {
            $installedAt = $provider->installedAt() ?? 'Unknown time';
        }

        $dataAttrs = 'data-provider="' . esc_attr($handle) . '"';
        $dataAttrs .= $isPackage ? ' data-provider-type="package"' : ' data-provider-type="theme"';
        $dataAttrs .= ' data-nonce="' . esc_attr(wp_create_nonce('meros_provider_install_operation_' . $handle)) . '"';

        if (!$enabled && $installed) {
            $html .= '<p style="margin-top:8px;">Installed: ' . esc_html($installedAt) . '</p>';
            $html .= '<a href="#" class="meros-provider-action-button meros-provider-uninstaller-button button button-primary" ' . $dataAttrs . ' style="margin-top:8px;">Uninstall</a>';
            $html .= '</div></div>';
            return $html;
        }

        if ($installed) {
            $html .= '<p style="margin-top:8px;">Installed: ' . esc_html($installedAt);

            $lastUpdated = $provider->lastUpdated();

            if ($lastUpdated !== $installedAt) {
                $html .= '<span> | Last updated: ' . esc_html($lastUpdated) . '</span>';
            }

            $html .= '</p>';
             
            if ($provider->hasUpdates()) {
                $html .= '<a href="#" class="meros-provider-action-button meros-provider-update-button button button-primary" ' . $dataAttrs . ' style="margin-top:8px;">Update</a>';
            }

        } else {
            $html .= '<a href="#" class="meros-provider-action-button meros-provider-installer-button button button-primary" ' . $dataAttrs . ' style="margin-top:8px;">Install</a>';
        }

        $html .= '</div></div>';
        return $html;
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
                'methods'             => [\WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'callback' => function (\WP_REST_Request $request) {
                    $view = sanitize_text_field($request->get_param('view'));
                    $data = $request->get_param('data');
                    
                    $attributes = [];
                    $viewData   = [];

                    if (!$view) {
                        return new \WP_Error('no_view', 'No view specified', ['status' => 400]);
                    }

                    if (is_array($data)) {
                        $attributes = $data;
                    } 
                    
                    elseif (is_string($data) && $data !== '') {
                        $decoded = json_decode(wp_unslash($data), true);

                        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                            return new \WP_Error('invalid_data', 'Invalid data payload', ['status' => 400]);
                        }

                        $attributes = $decoded;
                    }

                    $viewData = [
                        'attributes' => $attributes,
                        'data'       => $attributes,
                    ];

                    foreach ($attributes as $key => $value) {
                        $viewData[$key] = $this->normaliseRestViewData($value);
                    }

                    try {
                        $rendered = view($view, $viewData)->render();
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

    /**
     * Normalises REST view payload values for Blade rendering.
     *
     * Associative arrays are converted to objects so views can use property
     * access like $field->label, while list arrays are preserved.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normaliseRestViewData($value) {
        if (! is_array($value)) {
            return $value;
        }

        $normalised = array_map(fn ($item) => $this->normaliseRestViewData($item), $value);
        $isList = $normalised === [] || array_keys($normalised) === range(0, count($normalised) - 1);

        return $isList ? $normalised : (object) $normalised;
    }

    /***************************************************************
     * 
     * The following methods are used for AJAX calls from WP Admin
     * 
     ***************************************************************/
 
    private function initAdminAjaxHandlers(): void {
        add_action('wp_ajax_meros_provider_install_operation', [$this, 'handleProviderInstallerTasks']);
        // add_action('wp_ajax_meros_update_provider', [$this, 'handleProviderUpdate']);
        // add_action('wp_ajax_meros_uninstall_provider', [$this, 'handleProviderUninstallation']);
    }

    public function handleProviderInstallerTasks(): void {
        $provider     = sanitize_key($_POST['provider'] ?? '');
        $providerType = sanitize_key($_POST['providerType'] ?? '');
        $subAction    = $_POST['subAction'] ?? '';
        $nonce        = $_POST['nonce'] ?? '';

        $hasAction   = in_array($subAction, ['install', 'update', 'uninstall']);
        $hasProvider = is_string($provider) && $provider !== '';
        $isValidProviderType = in_array($providerType, ['package', 'theme']);

        $isValid = $hasAction && 
                   $hasProvider && 
                   $isValidProviderType && 
                   wp_verify_nonce($nonce, 'meros_provider_install_operation_' . $provider);

        if (!$isValid) {
            wp_send_json_error([
                'message' => 'Invalid request'
            ]);
        }

        if ($providerType === 'package') {
            $package = PackagesAccessor::get($provider);
            if ($package === null) {
                wp_send_json_error([
                    'message' => 'Package not found'
                ]);
                return;
            }

            try {
                switch ($subAction) {
                    case 'install':
                        $package->install();
                        break;
                    case 'update':
                        $package->update();
                        break;
                    case 'uninstall':
                        $package->uninstall();
                        break;
                }
            } catch (\Exception $e) {
                wp_send_json_error([
                    'message' => 'Error performing operation: ' . $e->getMessage(),
                ]);
                return;
            }

            wp_send_json_success([
                'message'  => 'Operation successful'
            ]);
        }

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