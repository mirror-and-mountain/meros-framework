<?php

namespace MM\Meros\Services\Integrations;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use MM\Meros\App\Framework;
use MM\Meros\App\Models\IntegrationAccount;
use MM\Meros\App\Models\IntegrationConnection;
use MM\Meros\Services\Contracts\Admin\Setting;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\Table;
use MM\Meros\Services\Registers\Integrations as IntegrationsRegister;
use MM\Meros\Support\Integrations\OAuthManager;
use MM\Meros\Support\Integrations\OAuthStateStore;

use MM\Meros\Facades\Theme as ThemeAccessor;
use MM\Meros\Facades\Packages as PackagesAccessor;

final class IntegrationsController {
    /**
     * Prefix used to identify encrypted integration settings in framework options.
     */
    private const ENCRYPTED_PREFIX = 'meros_enc:';

    /**
     * Sensitive integration fields cache, used to avoid repeated reflection calls for the same integration class.
     * 
     * @var array<string, array<int, string>>|null
     */
    private ?array $sensitiveIntegrationFieldsCache = null;

    /**
     * Registers option filters that transparently encrypt/decrypt sensitive integration settings.
     * 
     * @return void
     */
    public function initIntegrationSettingsProtection(): void {
        add_filter('pre_update_option_meros_framework_settings', [$this, 'encryptSensitiveIntegrationSettings'], 10, 3);
        add_filter('option_meros_framework_settings', [$this, 'decryptSensitiveIntegrationSettings']);
    }

    /**
     * Returns whether the global integrations feature is enabled in framework settings.
     * 
     * @return bool Whether the integrations feature is enabled.
     */
    public function integrationsFeatureEnabled(): bool {
        $settings = get_option('meros_framework_settings', []);

        return (bool) ($settings['integrations']['enable_integrations'] ?? false);
    }

    /**
     * Returns whether any integration has been enabled in framework settings.
     *
     * @return bool Whether any integration has been enabled.
     */
    public function hasEnabledIntegrationSettings(): bool {
        $settings = get_option('meros_framework_settings', []);
        $integrationSettings = $settings['integrations'] ?? [];

        if (!is_array($integrationSettings)) {
            return false;
        }

        foreach ($integrationSettings as $key => $value) {
            if ($key === 'enable_integrations') {
                continue;
            }

            if (is_array($value) && !empty($value[$key . '_enable'])) {
                return true;
            }

            if (is_string($key) && str_ends_with($key, '_enable') && !empty($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filters integration installer tables so they only appear when integrations are enabled.
     *
     * @param Table $table The table to check.
     * @param bool $hasEnabledIntegrations Whether any integrations are enabled.
     *
     * @return bool Whether the table should be included.
     */
    public function shouldIncludeInstallerTable(Table $table, bool $hasEnabledIntegrations): bool {
        $integrationTables = [
            'meros_integration_accounts',
            'meros_integration_connections',
            'meros_integration_environments',
        ];

        if (in_array($table->getTableName(), $integrationTables, true) && !$hasEnabledIntegrations) {
            return false;
        }

        return true;
    }

    /**
     * Registers admin-post handlers for integration OAuth actions.
     * 
     * @return void
     */
    public function initIntegrationOAuthHandlers(): void {
        add_action('admin_post_meros_integration_oauth_start', [$this, 'handleIntegrationOAuthStart']);
        add_action('admin_post_meros_integration_oauth_callback', [$this, 'handleIntegrationOAuthCallback']);
        add_action('admin_post_nopriv_meros_integration_oauth_callback', [$this, 'handleIntegrationOAuthCallback']);
        add_action('admin_post_meros_integration_oauth_disconnect', [$this, 'handleIntegrationOAuthDisconnect']);
    }

    /**
     * Configures settings for discovered integrations, allowing them to be enabled/disabled
     * and exposing integration-specific configuration fields.
     *
     * @param Framework $framework The framework instance.
     * @param Setting $settings The settings instance.
     *
     * @return void
     */
    public function configureIntegrationSettings(Framework $framework, Setting $settings): void {
        add_action('meros_providers_registered', function () use ($framework, $settings) {
            $integrations = $this->resolvedIntegrations($framework);

            $settings->add()->boolean('enable_integrations')
                ->label('Enable Integrations')
                ->description('Enable the Integrations feature to configure and manage integration connections.')
                ->default(false)
                ->field()
                    ->section('meros-features-integrations');

            if (!$this->integrationsFeatureEnabled()) {
                return;
            }

            // Always register all integration enable toggles.
            foreach ($integrations as $integration) {
                $integrationHandle = $integration->getHandle();
                $integrationEnabled = $this->integrationEnabled($integrationHandle);

                $enabledSetting = $settings->add()->boolean($integrationHandle . '_enable')
                    ->label('Enable ' . $integration->getLabel())
                    ->description($integration->getDescription())
                    ->default(false)
                    ->field()
                        ->section('meros-features-integrations');

                if ($integrationEnabled) {
                    $enabledSetting->titleHTML($this->getIntegrationSettingHTML($integration));
                }
            }

            // Only enabled integrations get configuration fields on their detail page.
            foreach ($integrations as $integration) {
                $integrationHandle = $integration->getHandle();
                $integrationPageSlug = $this->getIntegrationSettingsPageSlug($integrationHandle);
                $selectedEnvironment = $this->selectedIntegrationOAuthEnvironment($integrationHandle);
                $configurationFields = $integration->getConfigurationFields();
                $configurationFieldNames = array_values(array_map(fn ($field) => $field->getName(), $configurationFields));

                if (!$this->integrationEnabled($integrationHandle)) {
                    continue;
                }

                if ($integrationHandle !== 'salesforce') {
                    $settings->add(function ($setting) use ($integration) {
                        $setting->string($integration->getHandle() . '_base_uri')
                            ->field('text', function ($field) use ($integration) {
                                $field->label('Base URI');
                                $field->default($integration->getBaseUri());
                            })
                            ->section($this->getIntegrationSettingsPageSlug($integration->getHandle()));
                    });
                }

                $settings->add(function ($setting) use ($integration) {
                    $setting->string($integration->getHandle() . '_api_version')
                        ->field('text', function ($field) use ($integration) {
                            $field->label('API Version');
                            $field->default($integration->getApiVersion());
                        })
                        ->section($this->getIntegrationSettingsPageSlug($integration->getHandle()));
                });

                if ($integrationHandle !== 'salesforce') {
                    $settings->add(function ($setting) use ($integration) {
                        $setting->string($integration->getHandle() . '_connection_label')
                            ->field('text', function ($field) {
                                $field->label('Connection Label');
                                $field->helpText('Optional label used to pick a saved connection for fluent API calls.');
                            })
                            ->section($this->getIntegrationSettingsPageSlug($integration->getHandle()));
                    });
                }

                if ($integrationHandle === 'salesforce') {
                    $settings->add(function ($setting) use ($integration) {
                        $setting->string($integration->getHandle() . '_default_environment')
                            ->field('select', function ($field) {
                                $field->label('Default OAuth Environment');
                                $field->options([
                                    'production' => 'Production',
                                    'sandbox'    => 'Sandbox',
                                    'test'       => 'Test',
                                ]);
                                $field->default('production');
                                $field->helpText('Used when an environment is not explicitly selected in the OAuth connection panel.');
                            })
                            ->section($this->getIntegrationSettingsPageSlug($integration->getHandle()));
                    });
                }

                foreach ($configurationFields as $configurationField) {
                    $fieldName = $configurationField->getName();

                    if ($integrationHandle === 'salesforce' && $fieldName === 'default_environment') {
                        continue;
                    }

                    if (!$this->shouldRenderIntegrationConfigurationField($integration, $fieldName, $configurationFieldNames, $selectedEnvironment)) {
                        continue;
                    }

                    $settings->add(function ($setting) use ($integration, $configurationField, $integrationPageSlug) {
                        $configurationField->applyTo(
                            $setting,
                            $integration->getHandle() . '_' . $configurationField->getName()
                        );

                        $setting->section($integrationPageSlug);
                    });
                }
            }
        }, 10, 2);
    }

    /**
     * Renders integrations tab content for the Features page.
     * 
     * @param Framework $framework The framework instance.
     * 
     * @return void
     */
    public function renderIntegrationsTab(Framework $framework): void {
        $integrationHandle = sanitize_key($_GET['integration'] ?? '');
        $oauthStatus = sanitize_key($_GET['oauth_status'] ?? '');
        $oauthMessage = sanitize_text_field(wp_unslash($_GET['oauth_message'] ?? ''));

        if ($oauthStatus !== '') {
            echo $this->oauthStatusNoticeHtml($oauthStatus, $oauthMessage);
        }

        if ($integrationHandle !== '' && $this->integrationEnabled($integrationHandle)) {
            $integration = $this->resolvedIntegrations($framework)
                ->first(fn ($registeredIntegration) => $registeredIntegration->getHandle() === $integrationHandle);

            if ($integration !== null) {
                $selectedEnvironment = $this->selectedIntegrationOAuthEnvironment($integrationHandle);
                $isOauthIntegration = $integration->getAuthType() === 'oauth';
                $backUrl = add_query_arg([
                    'page' => 'meros-features',
                    'tab' => 'integrations',
                ], admin_url('options-general.php'));

                echo '<h2>' . esc_html($integration->getLabel()) . ' Configuration</h2>';
                echo '<p><a class="button button-secondary button-small" href="' . esc_url($backUrl) . '">Back to Integrations</a></p>';
                echo '<p><strong>Active environment:</strong> ' . esc_html($this->oauthEnvironmentLabel($selectedEnvironment)) . '</p>';

                $layoutClass = $isOauthIntegration
                    ? 'meros-integration-settings-layout'
                    : 'meros-integration-settings-layout meros-integration-settings-layout--full';

                echo '<div class="' . esc_attr($layoutClass) . '">';

                if ($isOauthIntegration) {
                    echo '<aside class="meros-integration-settings-sidebar">';
                    echo $this->getIntegrationOAuthSetupHTML($integration);
                    echo '</aside>';
                }

                echo '<section class="meros-integration-settings-main">';

                if ($isOauthIntegration && $this->shouldShowOAuthConfigurationWarning($integrationHandle)) {
                    echo '<div class="notice notice-warning inline meros-oauth-config-warning-main"><p>Set OAuth credentials for the selected environment, then save before connecting.</p></div>';
                }

                if ($integrationHandle === 'salesforce') {
                    $callbackUrl = $this->integrationOAuthCallbackUrl();
                    $derived = $this->salesforceDerivedEndpoints($selectedEnvironment);

                    echo '<div class="meros-oauth-config-meta">';
                    echo '<h3>OAuth App Setup</h3>';
                    echo '<p>Copy this callback URI into your Salesforce External Client App configuration.</p>';
                    echo '<label class="meros-oauth-meta-field"><span>Redirect URI</span><input type="text" readonly value="' . esc_attr($callbackUrl) . '" onclick="this.select();"/></label>';

                    if ($derived !== null) {
                        echo '<div class="meros-oauth-derived-grid">';
                        echo '<label class="meros-oauth-meta-field"><span>Derived Authorize URL</span><input type="text" readonly value="' . esc_attr($derived['authorize_url']) . '" onclick="this.select();"/></label>';
                        echo '<label class="meros-oauth-meta-field"><span>Derived Token URL</span><input type="text" readonly value="' . esc_attr($derived['token_url']) . '" onclick="this.select();"/></label>';
                        echo '<label class="meros-oauth-meta-field"><span>Derived Base API URL</span><input type="text" readonly value="' . esc_attr($derived['base_uri']) . '" onclick="this.select();"/></label>';
                        echo '</div>';
                    } else {
                        echo '<p class="description">Set Salesforce Org Domain for ' . esc_html($this->oauthEnvironmentLabel($selectedEnvironment)) . ' to preview derived OAuth endpoints.</p>';
                    }

                    echo '</div>';

                    echo '<div class="meros-oauth-settings-guide">';
                    echo '<h4>Global Salesforce Settings</h4>';
                    echo '<p class="description">Default OAuth Environment and Scopes apply across all environments.</p>';
                    echo '<h4>Environment-Specific Credentials</h4>';
                    echo '<p class="description">Org Domain, Client ID, and Client Secret are scoped to the active environment shown above.</p>';
                    echo '</div>';
                }

                settings_fields('meros_framework_settings_container');
                echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr(add_query_arg([
                    'page' => 'meros-features',
                    'tab' => 'integrations',
                    'integration' => $integrationHandle,
                    'oauth_environment' => $selectedEnvironment,
                ], admin_url('options-general.php'))) . '">';
                do_settings_sections($this->getIntegrationSettingsPageSlug($integrationHandle));
                submit_button();

                echo '</section>';
                echo '</div>';

                return;
            }
        }

        settings_fields('meros_framework_settings_container');
        do_settings_sections('meros-features-integrations');
        submit_button();
    }

    /**
     * Returns a collection of all resolved integrations from the framework and registered providers.
     *
     * @param Framework $framework The framework instance.
     *
     * @return Collection A collection of resolved integration instances.
     */
    private function resolvedIntegrations(Framework $framework): Collection {
        $providers = collect([$framework, ThemeAccessor::get()])
            ->merge(PackagesAccessor::all() ?? [])
            ->filter(fn ($provider) => $provider instanceof FeatureProvider)
            ->values();

        /** @var IntegrationsRegister $integrationsRegister */
        $integrationsRegister = app(IntegrationsRegister::class);
        $integrations = collect([]);

        foreach ($providers as $provider) {
            $integrationsRegister->checkout($provider);
            $integrations = $integrations->merge($integrationsRegister->allResolved());
        }

        return $integrations->unique(fn ($integration) => $integration->getHandle())->values();
    }

    /**
     * Returns a collection of all resolved integrations from the framework and registered providers.
     *
     * @return Collection A collection of resolved integration instances.
     */
    private function resolvedIntegrationsFromRuntime(): Collection {
        try {
            /** @var Framework|null $framework */
            $framework = app()->bound(Framework::class) ? app(Framework::class) : null;

            $providers = collect([$framework, ThemeAccessor::get()])
                ->merge(PackagesAccessor::all() ?? [])
                ->filter(fn ($provider) => $provider instanceof FeatureProvider)
                ->values();

            /** @var IntegrationsRegister $integrationsRegister */
            $integrationsRegister = app(IntegrationsRegister::class);
            $integrations = collect([]);

            foreach ($providers as $provider) {
                $integrationsRegister->checkout($provider);
                $integrations = $integrations->merge($integrationsRegister->allResolved());
            }

            return $integrations->unique(fn ($integration) => $integration->getHandle())->values();
        } catch (\Throwable $exception) {
            return collect([]);
        }
    }

    /**
     * Returns whether a specific integration is enabled in framework settings.
     *
     * @param string $integrationHandle The handle of the integration to check.
     *
     * @return bool Whether the integration is enabled.
     */
    private function integrationEnabled(string $integrationHandle): bool {
        if (!$this->integrationsFeatureEnabled()) {
            return false;
        }

        $settings = get_option('meros_framework_settings', []);
        $integrationSettings = $settings['integrations'] ?? [];

        if (!is_array($integrationSettings)) {
            return false;
        }

        $nested = $integrationSettings[$integrationHandle] ?? null;

        if (is_array($nested) && array_key_exists($integrationHandle . '_enable', $nested)) {
            return (bool) $nested[$integrationHandle . '_enable'];
        }

        return (bool) ($integrationSettings[$integrationHandle . '_enable'] ?? false);
    }

    /**
     * Returns the settings page slug for a specific integration.
     *
     * @param string $integrationHandle The handle of the integration.
     *
     * @return string The settings page slug for the integration.
     */
    private function getIntegrationSettingsPageSlug(string $integrationHandle): string {
        return 'meros-features-integration-' . sanitize_key($integrationHandle);
    }

    /**
     * Returns the HTML for the "Configure" link for a specific integration.
     *
     * @param object $integration The integration instance.
     *
     * @return string The HTML for the "Configure" link.
     */
    private function getIntegrationSettingHTML(object $integration): string {
        $href = add_query_arg([
            'page' => 'meros-features',
            'tab' => 'integrations',
            'integration' => $integration->getHandle(),
        ], admin_url('options-general.php'));

        return '<div class="meros-provider-links"><a href="' . esc_url($href) . '">Configure</a></div>';
    }

    /**
     * Returns the selected OAuth environment for a specific integration.
     *
     * @param string $integrationHandle The handle of the integration.
     *
     * @return string The selected OAuth environment.
     */
    private function selectedIntegrationOAuthEnvironment(string $integrationHandle): string {
        $requested = sanitize_key($_GET['oauth_environment'] ?? '');

        if ($requested !== '') {
            return $this->normalizeOauthEnvironment($requested);
        }

        $default = (string) $this->getIntegrationSettingValue($integrationHandle, 'default_environment', 'production');

        return $this->normalizeOauthEnvironment($default);
    }

    /**
     * Normalizes an OAuth environment string to a standard format.
     *
     * @param string $environment The environment string to normalize.
     *
     * @return string The normalized environment string.
     */
    private function normalizeOauthEnvironment(string $environment): string {
        $value = sanitize_key($environment);

        return match ($value) {
            'prod', 'live' => 'production',
            '' => 'production',
            default => $value,
        };
    }

    /**
     * Returns a human-readable label for a given OAuth environment.
     *
     * @param string $environment The environment string.
     *
     * @return string The human-readable label for the environment.
     */
    private function oauthEnvironmentLabel(string $environment): string {
        return match ($this->normalizeOauthEnvironment($environment)) {
            'production' => 'Production',
            'sandbox'    => 'Sandbox',
            'test'       => 'Test',
            default      => ucfirst($environment),
        };
    }

    /**
     * Retrieves the value of a specific integration setting, considering both prefixed and unprefixed keys.
     *
     * @param string $integrationHandle The handle of the integration.
     * @param string $key The key of the setting to retrieve.
     * @param mixed $default The default value to return if the setting is not found.
     *
     * @return mixed The value of the integration setting, or the default value if not found.
     */
    private function getIntegrationSettingValue(string $integrationHandle, string $key, mixed $default = null): mixed {
        $settings            = get_option('meros_framework_settings', []);
        $integrationSettings = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];
        $nested              = is_array($integrationSettings[$integrationHandle] ?? null) ? $integrationSettings[$integrationHandle] : [];
        $prefixed            = $integrationHandle . '_' . $key;

        if (array_key_exists($prefixed, $nested)) {
            return $nested[$prefixed];
        }

        if (array_key_exists($prefixed, $integrationSettings)) {
            return $integrationSettings[$prefixed];
        }

        if (array_key_exists($key, $nested)) {
            return $nested[$key];
        }

        if (array_key_exists($key, $integrationSettings)) {
            return $integrationSettings[$key];
        }

        return $default;
    }

    /**
     * Determines whether to show a warning about missing OAuth configuration for a specific integration.
     *
     * @param string $integrationHandle The handle of the integration.
     *
     * @return bool Whether to show the OAuth configuration warning.
     */
    private function shouldShowOAuthConfigurationWarning(string $integrationHandle): bool {
        $selectedEnvironment = $this->selectedIntegrationOAuthEnvironment($integrationHandle);
        $clientId = trim((string) $this->getIntegrationSettingValue($integrationHandle, 'client_id_' . $selectedEnvironment, ''));
        $clientSecret = trim((string) $this->getIntegrationSettingValue($integrationHandle, 'client_secret_' . $selectedEnvironment, ''));
        $orgDomain = trim((string) $this->getIntegrationSettingValue($integrationHandle, 'org_domain_' . $selectedEnvironment, ''));

        if ($clientId === '') {
            return true;
        }

        if ($integrationHandle === 'salesforce') {
            return $clientSecret === '' || $orgDomain === '';
        }

        $authorizeUrlEnvironment = trim((string) $this->getIntegrationSettingValue($integrationHandle, 'authorize_url_' . $selectedEnvironment, ''));
        $tokenUrlEnvironment     = trim((string) $this->getIntegrationSettingValue($integrationHandle, 'token_url_' . $selectedEnvironment, ''));

        $authorizeUrl = $authorizeUrlEnvironment !== ''
            ? $authorizeUrlEnvironment
            : trim((string) $this->getIntegrationSettingValue($integrationHandle, 'authorize_url', ''));

        $tokenUrl = $tokenUrlEnvironment !== ''
            ? $tokenUrlEnvironment
            : trim((string) $this->getIntegrationSettingValue($integrationHandle, 'token_url', ''));

        return $authorizeUrl === '' || $tokenUrl === '';
    }

    /**
     * Determines whether a specific integration configuration field should be rendered based on the selected OAuth environment.
     *
     * @param object $integration The integration instance.
     * @param string $fieldName The name of the configuration field.
     * @param array  $allFieldNames An array of all configuration field names for the integration.
     * @param string $selectedEnvironment The currently selected OAuth environment.
     *
     * @return bool Whether the configuration field should be rendered.
     */
    private function shouldRenderIntegrationConfigurationField(object $integration, string $fieldName, array $allFieldNames, string $selectedEnvironment): bool {
        if (!method_exists($integration, 'getAuthType') || $integration->getAuthType() !== 'oauth') {
            return true;
        }

        $environmentAwareBases = ['authorize_url', 'token_url', 'base_uri', 'instance_url', 'org_domain', 'client_id', 'client_secret'];
        $selected = $this->normalizeOauthEnvironment($selectedEnvironment);

        foreach ($environmentAwareBases as $base) {
            if (!str_starts_with($fieldName, $base)) {
                continue;
            }

            $hasEnvironmentVariants = collect($allFieldNames)
                ->contains(fn ($candidate) => preg_match('/^' . preg_quote($base, '/') . '_(production|prod|sandbox|test|live)$/', (string) $candidate) === 1);

            if (!$hasEnvironmentVariants) {
                return true;
            }

            if ($fieldName === $base) {
                return $selected === 'production'
                    && !in_array($base . '_production', $allFieldNames, true)
                    && !in_array($base . '_prod', $allFieldNames, true);
            }

            if (preg_match('/^' . preg_quote($base, '/') . '_(production|prod|sandbox|test|live)$/', $fieldName, $matches) !== 1) {
                return true;
            }

            $fieldEnvironment = $this->normalizeOauthEnvironment((string) ($matches[1] ?? 'production'));

            return $fieldEnvironment === $selected;
        }

        return true;
    }

    /**
     * Generates the HTML for the OAuth setup panel for a specific integration.
     *
     * @param object $integration The integration instance.
     *
     * @return string The generated HTML for the OAuth setup panel.
     */
    private function getIntegrationOAuthSetupHTML(object $integration): string {
        $handle = $integration->getHandle();
        $settings = get_option('meros_framework_settings', []);
        $integrationSettings = is_array($settings['integrations'] ?? null) ? $settings['integrations'] : [];

        $prefixedSetting = function (string $key, mixed $default = '') use ($integrationSettings, $handle) {
            $nested = $integrationSettings[$handle] ?? null;

            if (is_array($nested) && array_key_exists($handle . '_' . $key, $nested)) {
                return $nested[$handle . '_' . $key];
            }

            if (array_key_exists($handle . '_' . $key, $integrationSettings)) {
                return $integrationSettings[$handle . '_' . $key];
            }

            if (is_array($nested) && array_key_exists($key, $nested)) {
                return $nested[$key];
            }

            if (array_key_exists($key, $integrationSettings)) {
                return $integrationSettings[$key];
            }

            return $default;
        };

        $environments = [
            'production' => 'Production',
            'sandbox' => 'Sandbox',
            'test' => 'Test',
        ];

        $selectedEnvironment = $this->selectedIntegrationOAuthEnvironment($handle);

        if (!array_key_exists($selectedEnvironment, $environments)) {
            $selectedEnvironment = 'production';
        }

        $returnUrl = $this->normalizedIntegrationsReturnUrl('', $handle);
        $environmentSwitchUrl = $this->normalizedIntegrationsReturnUrl('', $handle);

        $environmentSelectId = 'meros-oauth-env-' . sanitize_html_class($handle);

        $html = '';

        $html .= '<div class="meros-integration-oauth-panel">';
        $html .= '<h3>OAuth Connection</h3>';
        $html .= '<p class="meros-oauth-intro">Connect, reconnect, or disconnect accounts for this integration. OAuth state is validated and tokens are stored encrypted.</p>';

        if ($handle === 'salesforce') {
            $html .= '<p class="meros-oauth-readonly-note"><strong>PKCE:</strong> selected per connection using the Use PKCE toggle when connecting. Reconnect defaults to PKCE on for Salesforce.</p>';
        }

        $html .= '<div class="meros-oauth-env-row">';
        $html .= '<label class="meros-oauth-field"><span>Environment</span><select id="' . esc_attr($environmentSelectId) . '" name="oauth_environment" onchange="(function(){var select=document.getElementById(\'' . esc_js($environmentSelectId) . '\');if(!select){return;}window.location.href=\'' . esc_js($environmentSwitchUrl) . '&oauth_environment=\'+encodeURIComponent(select.value);}())">';

        foreach ($environments as $value => $label) {
            $selected = $value === $selectedEnvironment ? ' selected' : '';
            $html .= '<option value="' . esc_attr($value) . '"' . $selected . '>' . esc_html($label) . '</option>';
        }

        $html .= '</select></label>';
        $html .= '</div>';

        $startNonce = wp_create_nonce('meros_integration_oauth_start_' . $handle);
        $startBaseUrl = add_query_arg([
            'action'             => 'meros_integration_oauth_start',
            'integration_handle' => $handle,
            'environment'        => $selectedEnvironment,
            'return_url'         => $returnUrl,
            '_wpnonce'           => $startNonce,
        ], admin_url('admin-post.php'));

        $accountLabelId = 'meros-oauth-account-label-' . sanitize_html_class($handle);
        $connectionLabelId = 'meros-oauth-connection-label-' . sanitize_html_class($handle);
        $pkceCheckboxId = 'meros-oauth-pkce-' . sanitize_html_class($handle);
        $connectButtonId = 'meros-oauth-connect-' . sanitize_html_class($handle);

        $html .= '<div class="meros-oauth-connect-form">';
        $html .= '<label class="meros-oauth-field"><span>Account Label</span><input id="' . esc_attr($accountLabelId) . '" class="regular-text" type="text" value="default"></label>';
        $html .= '<label class="meros-oauth-field"><span>Connection Label</span><input id="' . esc_attr($connectionLabelId) . '" class="regular-text" type="text" value="default"></label>';
        $html .= '<label class="meros-oauth-checkbox"><input id="' . esc_attr($pkceCheckboxId) . '" type="checkbox" value="1">Use PKCE</label>';
        $html .= '<div class="meros-oauth-connect-actions">';
        $html .= '<button id="' . esc_attr($connectButtonId) . '" class="button button-primary meros-oauth-connect-button" type="button" data-start-url="' . esc_attr($startBaseUrl) . '">Connect</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<script>(function(){var btn=document.getElementById("' . esc_js($connectButtonId) . '");if(!btn){return;}btn.addEventListener("click",function(){var account=document.getElementById("' . esc_js($accountLabelId) . '");var connection=document.getElementById("' . esc_js($connectionLabelId) . '");var pkce=document.getElementById("' . esc_js($pkceCheckboxId) . '");var base=btn.getAttribute("data-start-url")||"";if(base===""){return;}var query=[];query.push("account_label="+encodeURIComponent(account&&account.value!==""?account.value:"default"));query.push("connection_label="+encodeURIComponent(connection&&connection.value!==""?connection.value:"default"));if(pkce&&pkce.checked){query.push("pkce=1");}window.location.href=base+(base.indexOf("?")===-1?"?":"&")+query.join("&");});})();</script>';

        $accounts = IntegrationAccount::query()
            ->where('integration_handle', $handle)
            ->with('connections')
            ->orderBy('environment')
            ->orderBy('label')
            ->get();

        if ($accounts->isEmpty()) {
            $html .= '<p><em>No saved OAuth accounts yet.</em></p>';
        } else {
            $html .= '<div class="meros-oauth-table-wrap">';
            $html .= '<table class="widefat striped meros-oauth-table"><thead><tr>';
            $html .= '<th>Account</th><th>Environment</th><th>Connection</th><th>Status</th><th>Expires</th><th>Actions</th>';
            $html .= '</tr></thead><tbody>';

            foreach ($accounts as $account) {
                foreach ($account->connections as $connection) {
                    $expiresAt = $connection->token_expires_at?->format('Y-m-d H:i:s') ?? 'n/a';
                    $status = trim((string) ($connection->status ?? ($connection->is_active ? 'active' : 'inactive')));
                    $status = $status !== '' ? $status : 'inactive';
                    $errorSummary = trim((string) ($connection->last_error ?? ''));
                    $statusClass = match ($status) {
                        'active', 'connected', 'token_refreshed' => 'is-active',
                        'error' => 'is-error',
                        'disconnected' => 'is-disconnected',
                        default => 'is-disconnected',
                    };

                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($account->label) . '</td>';
                    $html .= '<td>' . esc_html($account->environment ?: 'production') . '</td>';
                    $html .= '<td>' . esc_html($connection->label) . '</td>';
                    $html .= '<td class="meros-oauth-status-cell"><span class="meros-oauth-status ' . esc_attr($statusClass) . '">' . esc_html($status) . '</span>' . ($errorSummary !== '' ? '<small class="meros-oauth-error-note">' . esc_html(Str::limit($errorSummary, 120)) . '</small>' : '') . '</td>';
                    $html .= '<td>' . esc_html($expiresAt) . '</td>';
                    $html .= '<td class="meros-oauth-actions-cell"><div class="meros-oauth-actions">';

                    $reconnectUrl = add_query_arg([
                        'action'                  => 'meros_integration_oauth_start',
                        'integration_handle'      => $handle,
                        'environment'             => $account->environment ?: 'production',
                        'account_label'           => $account->label,
                        'connection_label'        => $connection->label,
                        'reconnect_connection_id' => (string) $connection->getKey(),
                        'return_url'              => $returnUrl,
                        '_wpnonce'                => $startNonce,
                    ], admin_url('admin-post.php'));

                    $html .= '<a class="button button-small" href="' . esc_url($reconnectUrl) . '">Reconnect</a> ';

                    $disconnectNonce = wp_create_nonce('meros_integration_oauth_disconnect_' . $connection->getKey());

                    $disconnectUrl = add_query_arg([
                        'action'        => 'meros_integration_oauth_disconnect',
                        'connection_id' => (string) $connection->getKey(),
                        'return_url'    => $returnUrl,
                        '_wpnonce'      => $disconnectNonce,
                    ], admin_url('admin-post.php'));

                    $html .= '<a class="button button-small" href="' . esc_url($disconnectUrl) . '">Disconnect</a>';

                    $html .= '</div></td>';
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Starts an OAuth authorization redirect for an integration connection.
     * 
     * @return void
     */
    public function handleIntegrationOAuthStart(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage integrations.', 403);
        }

        $integrationHandle = sanitize_key($_REQUEST['integration_handle'] ?? '');

        if ($integrationHandle === '') {
            wp_die('Integration handle is required.', 400);
        }

        check_admin_referer('meros_integration_oauth_start_' . $integrationHandle);

        $returnUrl = esc_url_raw((string) ($_REQUEST['return_url'] ?? ''));

        try {
            $redirect = $this->oauthManager()->buildAuthorizationRedirect($integrationHandle, [
                'environment'             => sanitize_key($_REQUEST['environment'] ?? ''),
                'account_label'           => sanitize_text_field($_REQUEST['account_label'] ?? ''),
                'connection_label'        => sanitize_text_field($_REQUEST['connection_label'] ?? ''),
                'return_url'              => $returnUrl,
                'pkce'                    => !empty($_REQUEST['pkce']),
                'reconnect_connection_id' => absint($_REQUEST['reconnect_connection_id'] ?? 0),
            ]);

            $authorizationUrl = esc_url_raw((string) ($redirect['url'] ?? ''));

            if ($authorizationUrl === '' || !str_starts_with($authorizationUrl, 'http')) {
                throw new \RuntimeException('OAuth authorization URL is invalid.');
            }

            // OAuth providers redirect to external domains, so use wp_redirect instead of wp_safe_redirect.
            wp_redirect($authorizationUrl);
        } catch (\Throwable $exception) {
            report($exception);

            $targetUrl = $this->normalizedIntegrationsReturnUrl($returnUrl, $integrationHandle);

            wp_safe_redirect(add_query_arg([
                'oauth_status'  => 'error',
                'oauth_message' => 'Unable to start OAuth flow: ' . $exception->getMessage(),
            ], $targetUrl));
        }

        exit;
    }

    /**
     * Handles OAuth callback, token exchange, and connection persistence.
     * 
     * @return void
     */
    public function handleIntegrationOAuthCallback(): void {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_die('You must be an administrator to complete this OAuth connection.', 403);
        }

        try {
            $result = $this->oauthManager()->handleCallback($_GET);

            $integrationHandle = sanitize_key($result['integration_handle'] ?? '');
            $returnUrl = esc_url_raw((string) ($result['return_url'] ?? ''));

            $returnUrl = $this->normalizedIntegrationsReturnUrl($returnUrl, $integrationHandle);

            wp_safe_redirect(add_query_arg([
                'oauth_status'  => 'success',
                'oauth_message' => 'OAuth connection established successfully.',
            ], $returnUrl));
        } catch (\Throwable $exception) {
            report($exception);

            $integrationHandle = sanitize_key($_GET['integration'] ?? '');

            if ($integrationHandle === '') {
                $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));

                if ($state !== '') {
                    $statePayload = app(OAuthStateStore::class)->peek($state);
                    $integrationHandle = sanitize_key($statePayload['integration_handle'] ?? '');
                }
            }

            $fallbackUrl = $this->normalizedIntegrationsReturnUrl('', $integrationHandle);

            wp_safe_redirect(add_query_arg([
                'oauth_status'  => 'error',
                'oauth_message' => $exception->getMessage(),
            ], $fallbackUrl));
        }

        exit;
    }

    /**
     * Disconnects a saved OAuth connection.
     * 
     * @return void
     */
    public function handleIntegrationOAuthDisconnect(): void {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage integrations.', 403);
        }

        $connectionId = absint($_REQUEST['connection_id'] ?? 0);

        if ($connectionId <= 0) {
            wp_die('Connection id is required.', 400);
        }

        check_admin_referer('meros_integration_oauth_disconnect_' . $connectionId);

        $returnUrl = esc_url_raw((string) ($_REQUEST['return_url'] ?? ''));

        $returnUrl = $this->normalizedIntegrationsReturnUrl($returnUrl);

        try {
            $connection = IntegrationConnection::query()->with('account')->findOrFail($connectionId);
            $this->oauthManager()->disconnectConnection($connection);

            wp_safe_redirect(add_query_arg([
                'oauth_status'  => 'success',
                'oauth_message' => 'Connection disconnected.',
            ], $returnUrl));
        } catch (\Throwable $exception) {
            report($exception);

            wp_safe_redirect(add_query_arg([
                'oauth_status'  => 'error',
                'oauth_message' => 'Disconnect failed: ' . $exception->getMessage(),
            ], $returnUrl));
        }

        exit;
    }

    /**
     * Encrypts sensitive integration settings before they are persisted to wp_options.
     * 
     * @param mixed  $value    The new value being saved.
     * @param mixed  $oldValue The old value being replaced.
     * @param string $option   The name of the option being saved.
     * 
     * @return mixed The potentially modified value to be saved.
     */
    public function encryptSensitiveIntegrationSettings(mixed $value, mixed $oldValue, string $option): mixed {
        if ($option !== 'meros_framework_settings' || !is_array($value)) {
            return $value;
        }

        $integrations = $value['integrations'] ?? null;

        if (!is_array($integrations)) {
            return $value;
        }

        $value['integrations'] = $this->transformSensitiveValues($integrations, true);

        return $value;
    }

    /**
     * Decrypts sensitive integration settings when reading from wp_options.
     * 
     * @param mixed $value The value being read from the option.
     * 
     * @return mixed The potentially modified value with decrypted sensitive fields.
     */
    public function decryptSensitiveIntegrationSettings(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }

        $integrations = $value['integrations'] ?? null;

        if (!is_array($integrations)) {
            return $value;
        }

        $value['integrations'] = $this->transformSensitiveValues($integrations, false);

        return $value;
    }

    /**
     * Transforms sensitive integration settings by encrypting or decrypting them based on the $encrypt flag.
     *
     * @param array   $settings The integration settings to be transformed.
     * @param boolean $encrypt  Whether to encrypt (true) or decrypt (false) the sensitive values.
     *
     * @return array The transformed integration settings.
     */
    private function transformSensitiveValues(array $settings, bool $encrypt): array {
        $sensitiveFields = $this->mergeSensitiveFieldMaps(
            $this->sensitiveIntegrationFields(),
            $this->fallbackSensitiveIntegrationFields($settings)
        );

        foreach ($sensitiveFields as $integrationHandle => $fieldNames) {
            foreach ($fieldNames as $fieldName) {
                $prefixed = $integrationHandle . '_' . $fieldName;

                if (array_key_exists($prefixed, $settings) && is_string($settings[$prefixed])) {
                    $settings[$prefixed] = $encrypt
                        ? $this->encryptSensitiveValue($settings[$prefixed])
                        : $this->decryptSensitiveValue($settings[$prefixed]);
                }

                $nested = $settings[$integrationHandle] ?? null;

                if (!is_array($nested)) {
                    continue;
                }

                if (array_key_exists($prefixed, $nested) && is_string($nested[$prefixed])) {
                    $nested[$prefixed] = $encrypt
                        ? $this->encryptSensitiveValue($nested[$prefixed])
                        : $this->decryptSensitiveValue($nested[$prefixed]);
                }

                if (array_key_exists($fieldName, $nested) && is_string($nested[$fieldName])) {
                    $nested[$fieldName] = $encrypt
                        ? $this->encryptSensitiveValue($nested[$fieldName])
                        : $this->decryptSensitiveValue($nested[$fieldName]);
                }

                $settings[$integrationHandle] = $nested;
            }
        }

        return $settings;
    }

    /**
     * Retrieves a map of sensitive integration fields.
     *
     * @return array<string, array<int, string>> A map where the keys are integration handles and the values are arrays of sensitive field names.
     */
    private function sensitiveIntegrationFields(): array {
        if (is_array($this->sensitiveIntegrationFieldsCache) && $this->sensitiveIntegrationFieldsCache !== []) {
            return $this->sensitiveIntegrationFieldsCache;
        }

        $map = [];
        $integrations = $this->resolvedIntegrationsFromRuntime();

        foreach ($integrations as $integration) {
            $handle = sanitize_key((string) $integration->getHandle());

            if ($handle === '') {
                continue;
            }

            $sensitive = [];
            $configurationFields = is_array($integration->getConfigurationFields()) ? $integration->getConfigurationFields() : [];

            foreach ($configurationFields as $field) {
                if (!method_exists($field, 'isEncrypted') || !$field->isEncrypted()) {
                    continue;
                }

                $name = sanitize_key((string) $field->getName());

                if ($name === '') {
                    continue;
                }

                $sensitive[] = $name;
            }

            if ($sensitive !== []) {
                $map[$handle] = array_values(array_unique($sensitive));
            }
        }

        // Only cache non-empty maps. In admin requests, integrations may not be fully
        // registered on early option reads, and caching an empty map would skip encryption.
        if ($map !== []) {
            $this->sensitiveIntegrationFieldsCache = $map;
        }

        return $map;
    }

    /**
     * Adds safe fallback detection for secret-like integration keys in the settings payload.
     *
     * @param array $settings The integrations settings payload.
     *
     * @return array<string, array<int, string>> A map of integration handles to sensitive field names.
     */
    private function fallbackSensitiveIntegrationFields(array $settings): array {
        $map = [];

        foreach ($settings as $key => $value) {
            $keyString = sanitize_key((string) $key);

            if (preg_match('/^([a-z0-9_]+)_(client_secret(?:_[a-z0-9_]+)?|api_key(?:_[a-z0-9_]+)?|secret(?:_[a-z0-9_]+)?)$/', $keyString, $matches) === 1) {
                $handle = sanitize_key((string) ($matches[1] ?? ''));
                $fieldName = sanitize_key((string) ($matches[2] ?? ''));

                if ($handle !== '' && $fieldName !== '') {
                    $map[$handle][] = $fieldName;
                }
            }

            if (!is_array($value)) {
                continue;
            }

            $handle = $keyString;

            foreach ($value as $nestedKey => $nestedValue) {
                if (!is_string($nestedValue)) {
                    continue;
                }

                $nestedKeyString = sanitize_key((string) $nestedKey);

                if (
                    !str_starts_with($nestedKeyString, 'client_secret')
                    && !str_starts_with($nestedKeyString, 'api_key')
                    && !str_starts_with($nestedKeyString, 'secret')
                ) {
                    continue;
                }

                if ($handle !== '') {
                    $map[$handle][] = $nestedKeyString;
                }
            }
        }

        foreach ($map as $handle => $fields) {
            $map[$handle] = array_values(array_unique($fields));
        }

        return $map;
    }

    /**
     * Merges multiple sensitive-field maps into a single deduplicated map.
     *
     * @param array<string, array<int, string>> ...$maps Maps to merge.
     *
     * @return array<string, array<int, string>> The merged map.
     */
    private function mergeSensitiveFieldMaps(array ...$maps): array {
        $merged = [];

        foreach ($maps as $map) {
            foreach ($map as $handle => $fields) {
                $normalizedHandle = sanitize_key((string) $handle);

                if ($normalizedHandle === '' || !is_array($fields)) {
                    continue;
                }

                foreach ($fields as $field) {
                    $normalizedField = sanitize_key((string) $field);

                    if ($normalizedField === '') {
                        continue;
                    }

                    $merged[$normalizedHandle][] = $normalizedField;
                }
            }
        }

        foreach ($merged as $handle => $fields) {
            $merged[$handle] = array_values(array_unique($fields));
        }

        return $merged;
    }

    /**
     * Encrypts a sensitive value using AES-256-CBC encryption.
     *
     * @param string $value The value to encrypt.
     *
     * @return string The encrypted value, prefixed with the ENCRYPTED_PREFIX, or the original value if encryption fails.
     */
    private function encryptSensitiveValue(string $value): string {
        if ($value === '' || str_starts_with($value, self::ENCRYPTED_PREFIX) || !function_exists('openssl_encrypt')) {
            return $value;
        }

        $key = $this->encryptionKey();

        if ($key === null) {
            return $value;
        }

        try {
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

            if ($encrypted === false) {
                return $value;
            }

            return self::ENCRYPTED_PREFIX . base64_encode($iv . $encrypted);
        } catch (\Throwable $exception) {
            return $value;
        }
    }

    /**
     * Decrypts a sensitive value that was previously encrypted with AES-256-CBC.
     *
     * @param string $value The value to decrypt.
     *
     * @return string The decrypted value, or the original value if decryption fails.
     */
    private function decryptSensitiveValue(string $value): string {
        if (!str_starts_with($value, self::ENCRYPTED_PREFIX) || !function_exists('openssl_decrypt')) {
            return $value;
        }

        $payload = base64_decode(substr($value, strlen(self::ENCRYPTED_PREFIX)), true);

        if (!is_string($payload) || strlen($payload) <= 16) {
            return $value;
        }

        $key = $this->encryptionKey();

        if ($key === null) {
            return $value;
        }

        $iv = substr($payload, 0, 16);
        $ciphertext = substr($payload, 16);

        try {
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

            return is_string($decrypted) ? $decrypted : $value;
        } catch (\Throwable $exception) {
            return $value;
        }
    }

    /**
     * Retrieves the encryption key used for AES-256-CBC encryption.
     *
     * @return string|null The encryption key, or null if it cannot be determined.
     */
    private function encryptionKey(): ?string {
        if (function_exists('wp_salt')) {
            return hash('sha256', (string) wp_salt('auth'), true);
        }

        $appKey = getenv('APP_KEY');

        if (is_string($appKey) && $appKey !== '') {
            return hash('sha256', $appKey, true);
        }

        return null;
    }

    /**
     * Retrieves the OAuthManager instance from the application container.
     *
     * @return OAuthManager The OAuthManager instance.
     */
    private function oauthManager(): OAuthManager {
        return app(OAuthManager::class);
    }

    /**
     * Ensures integration redirects always land on the integrations tab, optionally scoped to one integration.
     *
     * @param string $returnUrl A caller-provided return URL.
     * @param string $integrationHandle Optional integration handle to keep context in admin.
     *
     * @return string The normalized integrations admin URL.
     */
    private function normalizedIntegrationsReturnUrl(string $returnUrl = '', string $integrationHandle = ''): string {
        $baseUrl = $returnUrl !== '' ? $returnUrl : admin_url('options-general.php');

        $args = [
            'page' => 'meros-features',
            'tab'  => 'integrations',
        ];

        if ($integrationHandle !== '') {
            $args['integration'] = $integrationHandle;
        }

        return add_query_arg($args, $baseUrl);
    }

    /**
     * Builds a consistent OAuth status notice block for integrations pages.
     *
     * @param string $oauthStatus The OAuth status slug, for example success or error.
     * @param string $oauthMessage Optional message to display.
     *
     * @return string
     */
    private function oauthStatusNoticeHtml(string $oauthStatus, string $oauthMessage = ''): string {
        $noticeClass = $oauthStatus === 'success' ? 'notice-success' : 'notice-error';
        $message = $oauthMessage !== ''
            ? $oauthMessage
            : ($oauthStatus === 'success' ? 'OAuth connection updated.' : 'OAuth operation failed.');

        return '<div class="notice ' . esc_attr($noticeClass) . ' inline"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Returns the default OAuth callback URL used by integration OAuth handlers.
     *
     * @return string
     */
    private function integrationOAuthCallbackUrl(): string {
        $base = admin_url('admin-post.php');
        return add_query_arg(['action' => 'meros_integration_oauth_callback'], $base);
    }

    /**
     * Builds derived Salesforce OAuth endpoints from the selected environment's org domain.
     *
     * @param string $environment The selected OAuth environment.
     *
     * @return array<string, string>|null
     */
    private function salesforceDerivedEndpoints(string $environment): ?array {
        $env = $this->normalizeOauthEnvironment($environment);
        $domain = trim((string) $this->getIntegrationSettingValue('salesforce', 'org_domain_' . $env, ''));

        if ($domain === '') {
            return null;
        }

        if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
            $domain = 'https://' . $domain;
        }

        $host = (string) parse_url($domain, PHP_URL_HOST);

        if ($host === '') {
            return null;
        }

        $base = 'https://' . trim($host, '/');

        return [
            'authorize_url' => $base . '/services/oauth2/authorize',
            'token_url'     => $base . '/services/oauth2/token',
            'base_uri'      => $base . '/services/data',
        ];
    }
}
