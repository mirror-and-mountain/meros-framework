<?php 

namespace MM\Meros\App\Admin;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

use MM\Meros\Services\Contracts\Admin\Setting;
use MM\Meros\Services\Controllers\SettingsController;

use MM\Meros\App\Models\ExternalConnection;

use MM\Meros\Facades\Integrations as IntegrationsAccessor;

use MM\Meros\App\Fields\Repeater;

use MM\Meros\Facades\Context;
use MM\Meros\Facades\Fields;

use MM\Meros\App\Admin\Concerns\MakesAdminBreadcrumbs;

class IntegrationSettingsController extends SettingsController {
    private int $integrationsCount = 0;
    private Repeater $connectionsField;

    use MakesAdminBreadcrumbs;

    // =========================================================================
    // Initialisation Methods
    // =========================================================================

    protected function load(): void {
        if (Context::isAdmin()) {
            $this->initAjaxHandlers();
        }

        $this->initConnectionsField();
        $this->initSettings();
        $this->initMenuPages();
    }

    private function initConnectionsField(): void {
        $this->connectionsField = Fields::checkout($this->authority)
            ->makeFrom('repeater', function ($field) {
                $field->id('meros_integrations_connections_repeater');
                $field->name('connections');
                $field->label('Connections');
                // $field->allowAdd(false);
                // $field->allowReorder(false);
                // $field->allowConfigure(false);
                $field->removeRowText('Revoke Connection');

                $field->onRemoveRow('function({ rowIndex, rowData }) {
                    console.log(rowData);

                    const data = new FormData();
                    data.append("action", "meros_integration_oauth_revoke");
                    data.append("integration", rowData.integration_id);
                    data.append("connection_label", rowData.label);
                    data.append("nonce", rowData.__row_nonce);

                    fetch(ajaxurl, {
                        method: "POST",
                        body: data
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success) {
                            alert("Error revoking connection: " + res.data.message);
                            return;
                        }

                        window.location.reload();
                    })
                    .catch(() => {
                        alert("An error occurred while revoking the connection. Please try again.");
                    });

                    return false;
                }');

                // $field->onConfigureRow(function (array $rowData, array $fields) {
                //     Log::debug('Configuring row for integration connection', ['rowData' => $rowData, 'fields' => $fields]);
                //     return '<p>This is a test call</p>';
                // });

                $field->subField('text', function ($subField) {
                    $subField->name('label');
                    $subField->label('Label');
                    // $subField->readonly();
                });

                $field->subField('text', function ($subField) {
                    $subField->name('environment');
                    $subField->label('Environment');
                    $subField->readonly();
                });

                $field->subField('text', function ($subField) {
                    $subField->name('connected_by');
                    $subField->label('Connected By');
                    $subField->readonly();
                });

                $field->subField('text', function ($subField) {
                    $subField->name('status');
                    $subField->label('Status');
                    $subField->readonly();
                });

                $field->subField('text', function ($subField) {
                    $subField->name('connected_at');
                    $subField->label('Connected At');
                    $subField->readonly();
                });

                $field->subField('text', function ($subField) {
                    $subField->name('integration_id');
                    $subField->label('Integration ID');
                    $subField->readonly();
                    $subField->hideInRepeaterTable();
                });
            });
    }

    private function initSettings(): void {
        $integrationsSettingsContainer = $this->settingsContainer('meros_integrations_settings');
        $this->configureIntegrationsSettings($integrationsSettingsContainer);
    }

    private function initMenuPages(): void {
        add_action('meros_providers_registered', function () {
            if ($this->integrationsCount > 0) {
                $this->configureIntegrationSettingsPage();
            }
        }, 10, 2);
    }

    // =========================================================================
    // Ajax Handlers
    // =========================================================================
    
    private function initAjaxHandlers(): void {
        add_action('wp_ajax_meros_integration_oauth_start', [$this, 'handleIntegrationOauthStart']);
        add_action('wp_ajax_meros_integration_oauth_revoke', [$this, 'handleIntegrationOauthRevoke']);
        add_action('wp_ajax_meros_integration_environment_change', [$this, 'handleIntegrationEnvironmentChange']);
    }

    /**
     * Handles AJAX requests for the initiation of an OAuth flow for integrations.
     *
     * @return void
     */
    public function handleIntegrationOauthStart(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Unauthorized'
            ]);
            return;
        }

        $integrationHandle = sanitize_key($_POST['integration'] ?? '');
        $returnUrl         = esc_url_raw((string) ($_POST['return_url'] ?? ''));
        $nonce             = $_POST['nonce'] ?? '';

        $hasHandle    = is_string($integrationHandle) && $integrationHandle !== '';
        $hasReturnUrl = is_string($returnUrl) && $returnUrl !== '';
        $isValid      = $hasHandle && $hasReturnUrl && wp_verify_nonce($nonce, 'meros_integration_oauth_start_' . $integrationHandle);

        if (!$isValid) {
            wp_send_json_error([
                'message' => 'Invalid request'
            ]);

            exit;
        }

        $integration = IntegrationsAccessor::get($integrationHandle);

        if ($integration === null) {
            wp_send_json_error([
                'message' => 'Integration not found.'
            ]);

            exit;
        }

        try {
            $authUrl = $integration->connect();
        } catch (\Exception $e) {
            Log::error('Error initiating OAuth flow for integration ' . $integrationHandle . ': ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Error initiating OAuth flow: ' . $e->getMessage(),
            ]);

            exit;
        }

        Log::debug('OAuth flow initiated successfully for integration: ' . $integrationHandle . ', redirecting to: ' . $authUrl);

        wp_send_json_success([
            'message' => 'OAuth flow initiated successfully.',
            'auth_url' => $authUrl,
        ]);
    }

    /**
     * Handles AJAX requests for revoking an OAuth connection for integrations.
     *
     * @return void
     */
    public function handleIntegrationOauthRevoke(): void {
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Unauthorized'
            ]);
            exit;
        }

        $integrationHandle = sanitize_key($_POST['integration'] ?? '');
        $connectionLabel   = sanitize_text_field($_POST['connection_label'] ?? '');
        $nonce             = $_POST['nonce'] ?? '';

        $isValid = 
            is_string($integrationHandle) && !empty($integrationHandle) &&
            is_string($connectionLabel) && !empty($connectionLabel) &&
            wp_verify_nonce($nonce, 'meros_integration_oauth_revoke_' . $integrationHandle . '_' . $connectionLabel);

        if (!$isValid) {
            wp_send_json_error([
                'message' => 'Invalid request.'
            ]);
            exit;
        }

        $integration = IntegrationsAccessor::get($integrationHandle);

        if ($integration === null) {
            wp_send_json_error([
                'message' => 'Integration not found.'
            ]);
            exit;
        }

        if (!method_exists($integration, 'revokeConnection')) {
            wp_send_json_error([
                'message' => 'This integration does not support connection revocation.'
            ]);
            exit;
        }

        $connection = ExternalConnection::where('integration_id', $integrationHandle)
            ->where('label', $connectionLabel)
            ->first();

        if ($connection === null) {
            wp_send_json_error([
                'message' => 'Connection not found.'
            ]);
            exit;
        }

        try {
            $integration->revokeConnection($connection);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => 'Error revoking connection: ' . $e->getMessage()
            ]);
            exit;
        }

        wp_send_json_success([
            'message' => 'Connection revoked successfully.'
        ]);
        exit;
    }

    /**
     * Handles AJAX requests for changing the environment of an integration.
     *
     * @return void
     */
    public function handleIntegrationEnvironmentChange(): void {
        $handle      = sanitize_key($_POST['integration'] ?? '');
        $environment = sanitize_key($_POST['environment'] ?? '');
        $nonce       = $_POST['nonce'] ?? '';

        $hasHandle = !empty($handle) && is_string($handle);
        $hasEnv    = !empty($environment) && is_string($environment);

        $isValid = $hasHandle &&
            $hasEnv &&
            wp_verify_nonce($nonce, 'meros_integration_select_environment_' . $handle);

        if (!$isValid) {
            wp_send_json_error([
                'message' => 'Invalid request.'
            ]);

            exit;
        }

        $integration = IntegrationsAccessor::get($handle);

        if ($integration === null) {
            wp_send_json_error([
                'message' => 'Integration not found.'
            ]);

            exit;
        }

        try {
            $integration->setCurrentEnvironment($environment);
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);

            exit;
        }

        wp_send_json_success([
            'message' => 'Integration environment change handled successfully.'
        ]);

        exit;
    }

    // =========================================================================
    // Settings Configuration
    // =========================================================================

    private function configureIntegrationsSettings(Setting $container): void {
        // add_action('init', function () use ($container) {
        //     dd($container->getValue(true), get_option('meros_integrations_settings'));
        // });
        add_action('meros_providers_registered', function () use ($container) {
            $registeredIntegrations = IntegrationsAccessor::getRegistered();

            foreach ($registeredIntegrations as $id => $integration) {
                $settingsContainer = $container
                    ->add()
                    ->object($id . '_settings');

                $instance       = IntegrationsAccessor::checkout($this->authority)->makeFrom($id, ['settings' => $settingsContainer]);
                $handle         = $instance->getHandle();
                $label          = $instance->getLabel();
                $description    = $instance->getDescription();

                $settingsContainer->name($handle . '_settings');
                $settingsContainer->label($label . ' Settings');

                $controls = $instance->getFields();

                if ($controls === []) {
                    continue;
                }

                $enabledSetting = $settingsContainer->add()->boolean($handle . '_enabled')
                    ->label('Enable ' . $label)
                    ->description($description)
                    ->default(false);

                if ($instance->isEnabled()) {
                    $enabledSetting->field()->titleHTML(
                        '<div class="meros-provider-links">
                            <span><a href="' . esc_url($instance->getIntegrationPageUrl()) . '">Manage</a> | </span>
                            <span class="description">Provided by ' . $instance->provider->getName() . '</span>
                        </div>',
                        ['meros-settings-label--wide']
                    )->page('meros-integrations');

                    if ($instance->hasMultipleEnvironments()) {
                        $environments = $instance->getEnvironments();

                        foreach ($environments as $envHandle => $envLabel) {
                            $environmentContainer = $settingsContainer
                                ->add()
                                ->object($handle . '_' . $envHandle . '_settings')
                                    ->label($envLabel . ' Settings');

                            foreach ($controls as $control) {
                                $this->makeIntegrationControl(
                                    $environmentContainer, 
                                    $handle, 
                                    $control, 
                                    ['handle' => $envHandle, 'label' => $envLabel]
                                );
                            }
                        }

                        $settingsContainer->add()->string($instance->getHandle() . '_current_environment')
                            ->label('Select Environment')
                            ->description('Select the environment for the ' . $label . ' integration.')
                            ->default(array_key_first($environments))
                            ->field('select')
                                ->options($environments)
                                ->attribute('data-integration', $handle)
                                ->attribute('data-nonce', wp_create_nonce('meros_integration_select_environment_' . $handle))
                                ->attribute('data-action', 'integration-select-environment')
                                ->page('meros-integrations-' . $handle);
                    }

                    else {
                        foreach ($controls as $control) {
                            $this->makeIntegrationControl($settingsContainer, $handle, $control);
                        }
                    }
                } 
                
                else {
                    $enabledSetting->field()->page('meros-integrations');
                }

                ++$this->integrationsCount;
            }
        }, 10, 2);
    }

    /**
     * Generates a control for an integration's setting based on the provided configuration and adds it to the specified settings container.
     *
     * @param Setting $container
     * @param string  $handle
     * @param array   $control
     * @param array   $environment
     *
     * @return void
     */
    private function makeIntegrationControl(Setting $container, string $handle, array $control, array $environment = []): void {
        $validTypes  = ['string', 'integer', 'boolean', 'number', 'array', 'object'];

        $type        = is_string($control['type'] ?? null) ? $control['type'] : null;
        $fieldType   = is_string($control['field_type'] ?? null) ? $control['field_type'] : null;
        $name        = is_string($control['name'] ?? null) ? $control['name'] : null;
        $label       = is_string($control['label'] ?? null) ? $control['label'] : null;
        $placeholder = is_string($control['placeholder'] ?? null) ? $control['placeholder'] : '';
        $description = is_string($control['description'] ?? null) ? $control['description'] : '';
        $default     = $control['default'] ?? null;
        $readonly    = is_bool($control['readonly'] ?? null) ? $control['readonly'] : false;
        $encrypt     = is_bool($control['encrypt'] ?? null) ? $control['encrypt'] : false;
        $options     = is_array($control['options'] ?? null) ? $control['options'] : null;

        if ($type === null || $name === null || !in_array($type, $validTypes)) {
            return;
        }

        if ($label === null) {
            $label = Str::title(str_replace('_', ' ', $name));
        }

        if ($environment !== [] && isset($environment['label'], $environment['handle'])) {
            $name = $name . '_' . $environment['handle'];
            $label .= ' (' . $environment['label'] . ')';
        }

        $setting = $container->add()->{$type}($name)
            ->label($label)
            ->description($description)
            ->default($default);

        if ($encrypt && $type === 'string') {
            $setting->encrypt();
        }

        $field = $setting->field($fieldType);

        if (!empty($placeholder) && method_exists($field, 'placeholder')) {
            $field->placeholder($placeholder);
        }
        
        if ($readonly && method_exists($field, 'attribute')) {
            $field->attribute('readonly', 'readonly');
        }

        if (Str::contains($fieldType, ['select', 'radio', 'checkbox']) && is_array($options)) {
            $field->options($options);
        }

        if (!empty($environment)) {
            $field->page('meros-integrations-' . $handle . '-' . $environment['handle']);
        } else {
            $field->page('meros-integrations-' . $handle);
        }
    }

    private function configureIntegrationSettingsPage(): void {
        $this->menuPages()->make(function ($page) {
            $page->slug('meros-integrations');
            $page->title('Integrations');
            $page->menuTitle('Integrations');
            $page->position(1);
            $page->showTitle(false);
            $page->showIntro(false);

            $page->callback(function () {
                $params = Context::params();
                $selectedIntegration = $params['integration'] ?? null;

                if ($selectedIntegration) {
                    $integration = IntegrationsAccessor::get($selectedIntegration);

                    if ($integration) {
                        $handle                     = $integration->getHandle();
                        $label                      = $integration->getLabel();
                        $authType                   = $integration->getAuthType();
                        $environments               = $integration->getEnvironments();
                        $settings                   = $integration->settings(true);
                        $hasMultipleEnvironments    = count($environments) > 1;
                        $currentEnvironment         = $integration->getCurrentEnvironment();
                        $connections                = $integration->getConnections();
                        $hasConnections             = count($connections) > 0;
                        $allowsMultipleConnections  = $integration->allowsMultipleConnections();

                        echo $this->getProviderSettingsBreadcrumbHTML(
                            admin_url('options-general.php?page=meros-integrations'),
                            'Integrations',
                            'integration',
                            $handle,
                            $label,
                            'settings'
                        );

                        echo '<h1>' . $label . ' Settings</h1>';
                        echo '<p>Manage the settings and configuration options for the ' . $label . ' integration.</p>';

                        if ($hasMultipleEnvironments) {
                            if ($currentEnvironment === null) {
                                $currentEnvironment = array_key_first($environments);
                            }

                            echo '<form method="post" action="options.php">';
                            settings_fields('meros_integrations_settings_container');
                            do_settings_sections('meros-integrations-' . Str::slug($handle));
                            do_settings_sections('meros-integrations-' . Str::slug($handle) . '-' . Str::slug($currentEnvironment));
                        }

                        else {
                            echo '<form method="post" action="options.php">';
                            settings_fields('meros_integrations_settings_container');
                            do_settings_sections('meros-integrations-' . Str::slug($handle));
                        }

                        $connectButtonAttributes = [];
                        if ($authType === 'oauth') {
                            $connectButtonAttributes = [
                                'data-meros-action' => 'oauth-start',
                                'data-integration'  => $handle, 
                                'data-return-url'   => admin_url('options-general.php?page=meros-integrations&integration=' . $handle),
                                'data-nonce'        => wp_create_nonce('meros_integration_oauth_start_' . $handle),
                            ];
                        }

                        if ($authType === 'oauth' && !$hasConnections) {
                            $requiredFields = $integration->getRequiredFields();

                            $disabled = false;
                            foreach ($requiredFields as $field) {
                                if (!isset($settings[$field['name']]) || 
                                    $settings[$field['name']] === null || 
                                    empty($settings[$field['name']])
                                ) {
                                    $disabled = true;
                                    break;
                                }
                            }

                            if ($disabled) {
                                $connectButtonAttributes['disabled'] = 'disabled';
                                echo '<p style="color:red;">To connect this application, make sure that you have provided the required settings.</p>';
                            }

                            echo '<div style="display:flex;gap:0.5rem;">';

                            submit_button();

                            submit_button(
                                'Connect', 
                                'primary', 
                                'connect_account', 
                                true, 
                                $connectButtonAttributes
                            );

                            echo '</div>';

                        } else if ($authType === 'oauth' && $hasConnections) {
                            echo '<div style="display:flex;gap:0.5rem;margin-bottom:1rem;">';
                            submit_button();

                            if ($allowsMultipleConnections) {
                                submit_button(
                                    'New Connection', 
                                    'primary', 
                                    'connect_account', 
                                    true,
                                    $connectButtonAttributes
                                );
                            }
                            echo '</div>';
                            echo '</form>';

                            echo '<h2>Connections</h2>';
                            echo '<p>Manage the connections for this integration.</p>';

                            $this->connectionsField->default(collect($connections)->map(function ($connection) {
                                return [
                                    'label' => $connection->label,
                                    'environment' => $connection->environment,
                                    'connected_by' => $connection->user?->display_name ?? '',
                                    'status' => $connection->status,
                                    'connected_at' => $connection->connected_at->format('Y-m-d H:i:s'),
                                    'integration_id' => $connection->integration_id,
                                ];
                            })->toArray());

                            echo $this->connectionsField->html(true, ['label' => false]);

                        } else {
                            submit_button();
                            echo '</form>';
                        }

                    } else {
                        echo '<h1>Integrations</h1>';
                        echo '<p>Integration not found.</p>';
                    }
                } 

                else {
                    echo '<h1>Integrations</h1>';
                    echo '<p>Manage the integrations available in your Meros-powered theme.</p>';

                    echo '<form method="post" action="options.php">';
                    settings_fields('meros_integrations_settings_container');
                    do_settings_sections('meros-integrations');
                    submit_button();
                    echo '</form>';
                }
            });
        })->in('options');
    }
}