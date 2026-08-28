<?php

namespace MM\Meros\Contracts\Features\Integrations;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use MM\Meros\Contracts\Features\Admin\Page;
use MM\Meros\Contracts\Features\Admin\Setting;

use MM\Meros\App\Models\ExternalConnection;
use MM\Meros\Facades\Support\Context;

// Todo: 
// Check whether user already has connection before reconnecting via authorisation flow.
// Add repeater for showing current connections
// Implement refresh and revoke flows
// Improve variable parameter/setting handling

abstract class OAuthIntegration extends Integration {
    /**
     * Configuration for the integrations base_uri setting
     *
     * @var array
     */
    protected array $baseUriSetting = [
        'name'        => 'base_uri',
        'label'       => 'Base URI',
        'description' => 'Set the base URI provided by the application.',
        'default'     => 'https://example.com'
    ];

    /**
     * Configuration for the integration's client_id setting
     *
     * @var array
     */
    protected array $clientIdSetting = [
        'name'        => 'client_id',
        'label'       => 'Client ID',
        'description' => 'Set the client ID provided by the application to authorize the integration.',
        'placeholder' => 'Enter your OAuth client ID',
        'default'     => ''
    ];

    /**
     * Configuration for the integration's client_secret setting
     *
     * @var array
     */
    protected array $clientSecretSetting = [
        'name'        => 'client_secret',
        'label'       => 'Client Secret',
        'description' => 'Set the client secret provided by the application to authorize the integration.',
        'placeholder' => 'Enter your OAuth client secret',
        'default'     => ''
    ];


    /**
     * The url that receives the integration's authorization callback.
     * 
     * @var string
     */
    final protected string $callbackUrl;

    /**
     * Configuration for the integration's callback_url setting.
     *
     * @var array
     */
    protected array $callbackUrlSetting = [
        'name'        => 'return_url',
        'label'       => 'Return URL',
        'description' => 'The URL you are redirected to after authorizing the integration. This URL should typically be set in the application\'s OAuth settings.'
    ];

    // ===================================================================================
    // Configuration for the integration's URIs
    // ===================================================================================
    
    protected string $tokenRefreshEndpoint = '{base_uri}/oauth/{api_version}/token/refresh';
    protected string $tokenRevokeEndpoint = '{base_uri}/oauth/{api_version}/token/revoke';

    // ===================================================================================
    // Configuration for the integration's authorisation flow
    // ===================================================================================

    /**
     * The applicaition endpoint to be redirected to for authorising the application
     *
     * @var string
     */
    protected string $authorizationEndpoint = '{base_uri}/oauth/{api_version}/authorize';

    /**
     * Parameters to be sent with the integration's authrorisation request.
     *
     * @var array
     */
    protected array $authorizationRequestParams = [
        'client_id'     => '{client_id}',
        'redirect_uri'  => '{return_url}',
        'response_type' => 'code'
    ];

    /**
     * Settings for the expected authorisation response.
     *
     * @var array
     */
    protected array $authorizationSettings = [
        'response_type'         => 'query', // or 'fragment'
        'error_response_type'   => 'query', // or 'fragment
        'code_key'              => 'code', // The parameter key that stores the authorisation code to exhange for a token
        'state_key'             => 'state',
        'error_key'             => 'error', // The parameter key that indicates whether there has been an authorisation error
        'error_description_key' => 'error_description' // The parameter that stores an error description if applicable
    ];

    // ===================================================================================
    // Configuration for the integration's token requests
    // ===================================================================================

    /**
     * The application endpoint for sending token requests to.
     *
     * @var string
     */
    protected string $tokenRequestEndpoint = '{base_uri}/oauth/{api_version}/token';

    /**
     * Headers to be sent with the token request.
     *
     * @var array
     */
    protected array $tokenRequestHeaders = [
        'Accept'       => 'application/json', // or 'application/x-www-form-urlencoded'
        'Content-Type' => 'application/x-www-form-urlencoded', // or 'application/json'
    ];
    
    /**
     * Settings for the token request.
     *
     * @var array
     */
    protected array $tokenRequestParams = [
        'method'        => 'POST',
        'grant_type'    => 'authorization_code',
        'code'          => '{code}',
        'redirect_uri'  => '{return_url}',
        'client_id'     => '{client_id}',
        'client_secret' => '{client_secret}',
        'format'        => 'form' // or 'json'
    ];

    /**
     * Settings for the expected token request response
     *
     * @var array
     */
    protected array $tokenRequestSettings = [
        'response_type' => 'json', // or 'form'
        'token_type'    => 'Bearer', // or 'MAC', 'Basic' etc.
        'access_token'  => 'access_token', // The key that contains the access token
        'id_token'      => 'id_token', // The response key that contains the id token (if applicable)
        'refresh_token' => 'refresh_token', // The response key that contains the refresh token (if applicable)
        'issued_at'     => 'issued_at', // The response key that contains when the token was issued (if applicable)
        'expires_at'    => 'expires_at', // The response key that contains when the token will expire (if applicable)
        'scope'         => 'scope' // The respose key that contains the tokens scopes (if applicable)
    ];

    // ===================================================================================
    // Initialisation
    // ===================================================================================
    
    final protected function whenConfigured(): void {
        $this->initCallback();
        $this->initAuthorisationCallback();
        $this->configureSettingsPage();
        parent::whenConfigured();
    }

    /**
     * Sets the callback url and handler for the integration's authorisation callback.
     *
     * @return void
     */
    private function initCallback(): void {
        $name = $this->getName();

        $this->callbackUrl = admin_url(
            "admin-post.php?action=meros_integration_oauth_authorisation_callback_{$name}"
        );

        $hook = "admin_post_meros_integration_oauth_authorisation_callback_{$name}";

        add_action($hook, function () {
            $this->authorise();
        });
    }

    private function initAuthorisationCallback(): void {
        $name   = $this->getName();
        $action = "meros_integration_oauth_start_{$name}";

        add_action("wp_ajax_{$action}", function () use ($action) {
            if (!check_ajax_referer($action, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid request.'], 403);
                exit;
            }

            wp_send_json_success([
                'authorisation_url' => $this->getAuthorisationUrl()
            ]);
            exit;
        });
    }

    /**
     * Configures the integration's settings page to include a connect button.
     *
     * @return void
     */
    private function configureSettingsPage(): void {
        $page = $this->menuPage;

        $page->hideSettings();
        $page->callback(function (Page $page) {
            echo '<form method="post" action="options.php">';
            settings_fields($page->getOptionGroup());
            do_settings_sections($page->getSlug());

            echo '<div style="display:flex;align-items:center;gap:1rem;margin-top:1rem;">';
            submit_button('Save Changes', 'primary', 'submit', false);
            submit_button('Connect', 'primary', 'meros-integration-connect', false, [
                'data-int-name' => $this->getName(),
                'data-nonce'    => wp_create_nonce('meros_integration_oauth_start_' . $this->getName())
            ]);
            echo '</div></form>';
        });
    }

    /**
     * Initialises the user-configurable settings for the integration.
     *
     * @return void
     */
    protected function initSettings(): void {
        $environments = $this->getEnvironments();

        if (empty($environments)) {
            $environments = $this->addEnvironment('default', 'Default');
        }

        $addSetting = function (
            string $prefix, 
            string $environment, 
            string $fieldType,
            array  $fieldParams = [],
            string $default = ''
        ) {
            $this->settings()->add('string', 
                function (Setting $setting) use ($prefix, $environment, $fieldType, $fieldParams, $default) {
                    $config = $this->{$prefix . 'Setting'};

                    $setting->name($config['name'] . "_{$environment}");
                    $setting->label($config['label'] . ' (' . ucfirst($environment) . ')');
                    $setting->description($config['description']);

                    if (!empty($config['placeholder'])) {
                        $fieldParams['placeholder'] = $config['placeholder'];
                    }

                    $default = !empty($default) ? $default : $config['default'] ?? '';
                    if (!empty($default)) {
                        $setting->default($default);
                    }

                    if ($environment !== $this->getCurrentEnvironment()) {
                        $fieldParams['attributes'] = ['data-hidden' => true];
                    }

                    $setting->field($fieldType, $fieldParams);
                }
            );
        };

        if (count($environments) > 1) {
            $switchAction = 'meros_switch_integration_environment';

            $this->settings()->add('string', function (Setting $setting) use ($environments, $switchAction) {
                $setting->name($this->currentEnvironmentSettingName);
                $setting->label('Current Environment');
                $setting->description('The current environment being used for connections to this service.');
                $setting->default(array_key_first($environments));
                $setting->field('select', ['options' => $environments])
                    ->attribute('data-meros-integration-env-switch', 'true')
                    ->attribute('data-action', $switchAction)
                    ->attribute('data-nonce', wp_create_nonce($switchAction . '_' . $this->getName()))
                    ->attribute('data-int-name', $this->getName());
            });
        }

        foreach ($environments as $handle => $label) {
            // The base uri setting
            $addSetting('baseUri', $handle, 'url');

            // The client id setting
            $addSetting('clientId', $handle, 'text');

            // The client secret setting
            $addSetting('clientSecret', $handle, 'password');

            // The callback url setting
            $addSetting('callbackUrl', $handle, 'url', ['readonly' => true], $this->callbackUrl);
        }
    }

    // ===================================================================================
    // Attribute Setters
    // ===================================================================================

    final protected function configureSetting(string $setting, array $config): void {
        if (!in_array($setting, ['base_uri', 'client_id', 'client_secret', 'callback_url'])) {
            return;
        }

        $property = Str::camel($setting) . 'Setting';
        $currentConfig = $this->{$property};

        foreach ($config as $key => $value) {
            if (!array_key_exists($key, $currentConfig)) {
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            $currentConfig[$key] = $value;
        }

        $this->{$property} = $currentConfig;
    }

    final protected function setupAuthorizationFlow(string $endpoint, array $parameters = [], array $settings = []): void {
        $this->authorizationEndpoint = $endpoint;

        if (!empty($parameters)) {
            $this->authorizationRequestParams = array_merge($this->authorizationRequestParams, $parameters);
        }

        if (!empty($parameters)) {
            $this->authorizationSettings = array_merge($this->authorizationSettings, $settings);
        }
    }

    final protected function setupTokenRequestFlow(string $endpoint, array $parameters = [], array $settings = []): void {
        $this->tokenRequestEndpoint = $endpoint;

        if (!empty($parameters)) {
            if (isset($parameters['headers']) && is_array($parameters['headers'])) {
                $this->tokenRequestHeaders = array_merge($this->tokenRequestHeaders, $parameters['headers']);
                unset($parameters['headers']);
            }

            if (!empty($parameters)) {
                $this->tokenRequestParams = array_merge($this->tokenRequestParams, $parameters);
            }
        }

        if (!empty($settings)) {
            $this->tokenRequestSettings = array_merge($this->tokenRequestSettings, $settings);
        }
    }

    // ===================================================================================
    // Authorisation Flow
    // ===================================================================================

    /**
     * Returns the integration's authorisation URL.
     * Should be handled by the requester to redirect to the service's authorisation flow.
     *
     * @return string
     */
    final public function getAuthorisationUrl(): string {
        return $this->buildRequestUrl(
            $this->authorizationEndpoint,
            $this->authorizationRequestParams
        );
    }

    /**
     * Handles the integration's authorisation callback.
     *
     * @return void
     */
    private function authorise(): void {
        if (!current_user_can('manage_options')) {
            wp_die("You don't have permission to carry out this operation");
        }

        $settings = $this->authorizationSettings;

        $pageSlug  = $this->getPage()->getSlug();
        $params    = Context::params();
        $error     = $params[$settings['error_key']] ?? null;
        $errorDesc = $params[$settings['error_description_key']] ?? null;

        if ($error !== null) {
            $message = $errorDesc ?? 'An error occurred during the OAuth callback.';
            wp_redirect($pageSlug . '&status=error&description=' . urlencode($message));
            exit;
        }

        $code = $params[$settings['code_key']] ?? null;

        if ($code === null) {
            $this->redirect('error', urlencode('Authorization code not provided.'));
        }

        // Exchange the authorisation code for an access token
        $tokenResponse = $this->exchangeCodeForToken($code);

        // Store the token response 
        $this->storeToken($tokenResponse);

        // Redirect to a success page or back to the integration settings
        $this->redirect('success');
    }

    /**
     * Exchanges an authorisation code passed from the authorisation callback for an access token.
     *
     * @param string $code
     *
     * @return array
     */
    private function exchangeCodeForToken(string $code): array {
        // Build the token endpoint url
        $tokenEndpoint = $this->buildRequestUrl($this->tokenRequestEndpoint);

        // Build the headers for the request
        $headers = $this->tokenRequestHeaders;

        // Build the request payload
        $params = $this->tokenRequestParams;
        $method = $params['method'] ?? 'POST';
        unset($params['method']);

        $payload = $this->buildRequestPayload($params, [
            'code' => $code
        ]);

        $response = $this->httpClient->send([
            'method'  => $method,
            'url'     => $tokenEndpoint,
            'headers' => $headers,
            'payload' => $payload,
            'format'  => $params['format'] ?? 'form'
        ]);

        if (!$response->successful()) {
            $this->redirect('error', 'Failed to exchange authorization code for token. HTTP Status: ' . $response->status());
            exit;
        }

        return $this->parseTokenResponseBody($response->body(), $this->tokenRequestSettings);
    }

    /**
     * Stores a token response, and other relevant integration data, as an entry in the
     * External Connections table.
     *
     * @param array  $tokenResponse
     *
     * @return void
     */
    private function storeToken(array $tokenResponse): void {
        $settings     = $this->tokenRequestSettings;
        $accessToken  = $tokenResponse[$settings['access_token']] ?? null;
        $idToken      = $tokenResponse[$settings['id_token']] ?? null;
        $refreshToken = $tokenResponse[$settings['refresh_token']] ?? null;
        $issuedAt     = $tokenResponse[$settings['issued_at']] ?? null;
        $expiresAt    = $tokenResponse[$settings['expires_at']] ?? null;
        $scopes       = $tokenResponse[$settings['scope']] ?? null;

        if ($accessToken !== null) {
            ExternalConnection::updateOrCreate([
                'label'            => $this->getLabel() . ' ' . now()->format('Y-m-d H:i:s'),
                'integration_id'   => $this->getName(),
                'environment'      => $this->getCurrentEnvironment(),
                'user_id'          => get_current_user_id(),
                'is_active'        => true,
                'access_token'     => $accessToken,
                'refresh_token'    => $refreshToken,
                'id_token'         => $idToken,
                'scopes'           => $scopes,
                'token_issued_at'  => $this->resolveTimestamp($issuedAt),
                'token_expires_at' => $this->resolveTimestamp($expiresAt),
                'last_used_at'     => now(),
                'connected_at'     => now(),
                'status'           => 'connected',
                'status_reason'    => 'Successfully connected via OAuth.',
                'metadata'         => array_filter($tokenResponse, function ($key) use ($settings) {
                    return !in_array($key, [
                        $settings['access_token'],
                        $settings['refresh_token'],
                        $settings['id_token'],
                        $settings['scope'],
                        $settings['issued_at'],
                        $settings['expires_at']
                    ], true);
                }, ARRAY_FILTER_USE_KEY),
            ]);
        } else {
            $this->redirect('error', 'Access token not found in the token response.');
        }
    }

    // ===================================================================================
    // Token Refresh Flow
    // ===================================================================================

    // ===================================================================================
    // Token Revoke Flow
    // ===================================================================================

    // ===================================================================================
    // Request Building
    // ===================================================================================

    /**
     * Builds a request url with the provided endpoint and optional additional query parameters.
     *
     * @param string $endpoint
     * @param array  $queryParams
     *
     * @return string
     */
    private function buildRequestUrl(string $endpoint, array $queryParams = []): string {
        if (Str::contains($endpoint, '{')) {
            $segments = explode('/', $endpoint);
            $segments = array_map(function ($segment) {
                if (Str::startsWith($segment, '{') && Str::endsWith($segment, '}')) {
                    $varName = trim($segment, '{}');
                    return $this->settings($varName);
                }
                return $segment;
            }, $segments);

            $endpoint = implode('/', $segments);
        }

        if (!empty($queryParams)) {

            foreach ($queryParams as $key => $value) {
                $queryParams[$key] = $this->sanitizeDynamicValue(
                    $this->resolveDynamicValue($value)
                );
            }

            $endpoint .= '?' . http_build_query($queryParams);
        }

        return $endpoint;
    }

    /**
     * Builds a request payload with the provided schema and any additional override values.
     *
     * @param array $schema
     * @param array $overrides
     *
     * @return array
     */
    private function buildRequestPayload(array $schema, array $overrides = []): array {
        $payload = [];

        foreach ($schema as $key => $value) {
            if (isset($overrides[$key])) {
                $payload[$key] = $overrides[$key];
            } else {
                $payload[$key] = $this->sanitizeDynamicValue(
                    $this->resolveDynamicValue($value)
                );
            }
        }

        return $payload;
    }

    /**
     * Resolves a dynamic value by checking if it is a string that starts and ends with curly braces, indicating that it is a variable. 
     * If it is, the method retrieves the corresponding setting value for that variable.
     *
     * @param mixed $value The value to resolve.
     *
     * @return mixed The resolved value, or the original value if it is not a dynamic variable.
     */
    private function resolveDynamicValue(mixed $value): mixed {
        if (is_string($value) && Str::startsWith($value, '{') && Str::endsWith($value, '}')) {
            $varName = trim($value, '{}');
            
            switch ($varName) {
                case 'base_uri':
                    $varName = $this->baseUriSetting['name'];
                    break;
                case 'client_id':
                    $varName = $this->clientIdSetting['name'];
                    break;
                case 'client_secret':
                    $varName = $this->clientSecretSetting['name'];
                    break;
                case 'return_url':
                    $varName = $this->callbackUrlSetting['name'];
                    break;
                default:
                    $varName = $varName;
            }

            return $this->settings($varName, true);
        }

        return $value;
    }

    /**
     * Sanitises a dynamic value by converting it to a string representation.
     *
     * @param mixed $value The value to sanitize.
     *
     * @return string The sanitized string representation of the value.
     */
    private function sanitizeDynamicValue(mixed $value): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    // ===================================================================================
    // Helpers
    // ===================================================================================

    /**
     * Parses a token request response body for handling.
     *
     * @param string $body
     * @param array  $settings
     *
     * @return array
     */
    private function parseTokenResponseBody(string $body, array $settings): array {
        if ($body === '') {
            return [];
        }

        $responseType = $settings['response_type'] ?? 'json';

        if ($responseType === 'form') {
            parse_str($body, $parsed);
            return is_array($parsed) ? $parsed : [];
        }

        $parsed = json_decode($body, true);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Resolves a timestamp into a value compatible with Laravel timestamp columns.
     *
     * Supports Unix seconds, Unix milliseconds, and date strings.
     */
    private function resolveTimestamp(mixed $value): ?Carbon {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;

                // 13-digit values are typically Unix milliseconds.
                if (abs($timestamp) >= 100000000000) {
                    return Carbon::createFromTimestampMs($timestamp, 'UTC');
                }

                return Carbon::createFromTimestamp($timestamp, 'UTC');
            }

            return Carbon::parse((string) $value, 'UTC');
        } catch (\Throwable $exception) {
            Log::warning('Unable to parse OAuth token timestamp.', [
                'value'     => $value,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function redirect(string $status, string $message = '') {
        $params = '&status=' . $status;
        if ($message !== '') {
            $params .= '&message=' . urlencode($message);
        }

        wp_redirect(admin_url('options-general.php?page=meros-integrations&integration=' . $this->getName('slug') . $params));
        exit;
    }
}