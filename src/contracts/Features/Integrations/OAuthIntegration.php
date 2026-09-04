<?php

namespace MM\Meros\Contracts\Features\Integrations;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use MM\Meros\Contracts\Features\Admin\Page;
use MM\Meros\Contracts\Features\Admin\Setting;

use MM\Meros\App\Models\ExternalConnection;
use MM\Meros\Facades\Support\Context;

use MM\Meros\Facades\Components\Fields;
use MM\Meros\App\Components\Fields\Repeater;

abstract class OAuthIntegration extends Integration {
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

    protected array $authorizationErrorKeys = [
        'error',
        'error_description',
        'error_code'
    ];

    // ===================================================================================
    // Configuration for the integration's URIs
    // ===================================================================================
    
    protected string $tokenRefreshEndpoint = '{base_uri}/oauth/{api_version}/token/refresh';
    protected string $tokenRevokeEndpoint = '{base_uri}/oauth/{api_version}/token/revoke';

    // ===================================================================================
    // Configuration for the integration's token requests
    // ===================================================================================

    /**
     * The expected format of a token request response. Used for parsing a token request response.
     *
     * @var string
     */
    protected string $tokenResponseFormat = 'json'; // or 'form'

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
    // Configuration for the integration's connections
    // ===================================================================================

    /**
     * An array of ExternalConnection models representing the current connections for this integration and environment.
     *
     * @var array<ExternalConnection>
     */
    private array $connections = [];

    /**
     * Indicates whether multiple connections are allowed per environment for this integration.
     *
     * @var boolean
     */
    protected bool $multipleConnectionsPerEnvironment = false;

    /**
     * Indicates whether connections for this integration are shared across all users or specific to the current user.
     *
     * @var string
     */
    protected string $audience = 'all_users'; // or 'current_user'

    // ===================================================================================
    // Initialisation
    // ===================================================================================

    final protected function init(): void {
        parent::init();

        $this->callbackUrl = admin_url(
            "admin-post.php?action=meros_integration_oauth_authorisation_callback_{$this->getName()}"
        );

        $this->initConnections();
        $this->initAuthorisationCallback();
        $this->initAuthorisationReturnCallback();
        $this->initRevokeConnectionCallback();
    }

    /**
     * Initialises the connections for this integration and environment.
     *
     * @return void
     */
    private function initConnections(): void {
        $this->connections = ExternalConnection::where('integration_id', $this->getName())
            ->where('environment', $this->getCurrentEnvironment())
            ->get()
            ->toArray();
    }

    /**
     * Initialises the callback for starting the integration's authorisation flow.
     *
     * @return void
     */
    private function initAuthorisationCallback(): void {
        $name   = $this->getName();
        $action = "meros_integration_oauth_start_{$name}";

        add_action("wp_ajax_{$action}", function () use ($action) {
            if (!check_ajax_referer($action, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid request.'], 403);
                exit;
            }

            $authUrl = $this->getAuthorisationUrl();
            if (empty($authUrl)) {
                wp_send_json_error(['message' => 'Could not generate authorisation URL.'], 400);
                exit;
            }

            wp_send_json_success([
                'authorisation_url' => $authUrl
            ]);
            exit;
        });
    }

    /**
     * Sets the callback url and handler for the integration's authorisation callback.
     *
     * @return void
     */
    private function initAuthorisationReturnCallback(): void {
        $name = $this->getName();
        $hook = "admin_post_meros_integration_oauth_authorisation_callback_{$name}";

        add_action($hook, function () {
            $this->authorise();
        });
    }

    /**
     * Initialises the callback for revoking an existing connection for this integration.
     *
     * @return void
     */
    private function initRevokeConnectionCallback(): void {
        $action = "meros_integration_revoke_connection_{$this->getName()}";

        add_action("wp_ajax_{$action}", function () use ($action) {
            if (!check_ajax_referer($action, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid request.'], 403);
                exit;
            }

            $connectionId = $_POST['connection_id'] ?? null;

            if (!$connectionId) {
                wp_send_json_error(['message' => 'Connection ID is required.'], 400);
                exit;
            }

            $connection = ExternalConnection::find($connectionId);

            if (!$connection) {
                wp_send_json_error(['message' => 'Connection not found.'], 404);
                exit;
            }

            if ($connection->status === 'revoked') {
                $connection->delete();
                wp_send_json_success(['message' => 'Connection deleted successfully.']);
                exit;
            }

            // Here you would typically call the external service's revoke endpoint
            // For now, we'll just mark the connection as revoked in the database
            $connection->is_active = false;
            $connection->status = 'revoked';
            $connection->status_reason = 'Revoked by user';
            $connection->revoked_at = now();
            $connection->save();

            wp_send_json_success(['message' => 'Connection revoked successfully.']);
            exit;
        });
    }

    /**
     * Initialises settings for OAuth integrations.
     *
     * @return void
     */
    final protected function afterInitConfigurableSettings(): void {
        // The callback url setting
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('return_url');
            $setting->label('Return URL');
            $setting->field('url', [
                'readonly' => true,
                'default'  => $this->callbackUrl
            ]);
        });

        $this->configureSettingsPage();

        add_action('admin_init', function () {
            $returnUrl = $this->settings('return_url');

            if ($returnUrl !== $this->callbackUrl) {
                $this->settings->setItemValue(
                    'return_url_' . $this->getCurrentEnvironment(), $this->callbackUrl
                );
            }
        });
    }

    /**
     * Configures the integration's settings page to include a connect button.
     *
     * @return void
     */
    private function configureSettingsPage(): void {
        $this->getConnectionsRepeater();
        $page = $this->menuPage;

        $page->hideSettings();
        $page->callback(function (Page $page) {
            if (isset($_GET['status'])) {
                $status = $_GET['status'];
                if ($status === 'exists') {
                    echo '<div class="notice notice-warning is-dismissible "><p>A connection already exists for this integration and environment.</p></div>';
                } elseif ($status === 'error') {
                    $message = $_GET['message'] ?? 'An error occurred during the OAuth process.';
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
                } elseif ($status === 'success') {
                    echo '<div class="notice notice-success is-dismissible"><p>Connection to ' . esc_html($this->getLabel()) . ' successful.</p></div>';
                } elseif ($status === 'connection_revoked') {
                    echo '<div class="notice notice-success is-dismissible"><p>Connection revoked successfully.</p></div>';
                } elseif ($status === 'connection_deleted') {
                    echo '<div class="notice notice-success is-dismissible"><p>Connection deleted successfully.</p></div>';
                }
            }
            echo 
                '<nav class="meros-breadcrumbs" aria-label="Breadcrumb">
                    <a href="' . admin_url('admin.php?page=meros-integrations') . '">Integrations</a>
                    <span class="meros-breadcrumb-separator">/ ' . $this->getLabel() . '</span>
                </nav>';
                
            echo '<form method="post" action="options.php">';
            settings_fields($page->getOptionGroup());
            do_settings_sections($page->getSlug());

            echo '<div style="display:flex;align-items:center;gap:1rem;margin-top:1rem;">';
            submit_button('Save Changes', 'primary', 'submit', false);
            if ($this->canConnect()) {
                submit_button('Connect', 'primary', 'meros-integration-connect', false, [
                    'data-int-name' => $this->getName(),
                    'data-nonce'    => wp_create_nonce('meros_integration_oauth_start_' . $this->getName())
                ]);
            }
            echo '</div></form>';

            echo $this->getConnectionsRepeater();
        });
    }

    /**
     * Gets the HTML for the connections repeater field shown on the integration setup page.
     *
     * @return string
     */
    private function getConnectionsRepeater(): string {
        $repeaterValue = [];

        foreach ($this->connections as $connection) {
            $repeaterValue[] = [
                'connection_id'             => $connection['id'],
                'connection_integration_id' => $connection['integration_id'],
                'connection_label'          => $connection['label'],
                'connection_environment'    => $connection['environment'],
                'connection_status'         => $connection['status'],
                'connection_connected_by'   => $connection['user_id'] ? get_userdata($connection['user_id'])->user_login : 'Unknown',
                'connection_last_used_at'   => $connection['last_used_at'] ? Carbon::parse($connection['last_used_at'])->toDateTimeString() : null,
                'connection_connected_at'   => $connection['connected_at'] ? Carbon::parse($connection['connected_at'])->toDateTimeString() : null,
                'connection_revoke_nonce'   => wp_create_nonce('meros_integration_revoke_connection_' . $connection['integration_id'])
            ];
        }

        $repeater = Fields::checkout($this->getProvider())
            ->makeFrom('repeater', function (Repeater $repeater) use ($repeaterValue) {
                $repeater->name($this->getName() . '_' . $this->getCurrentEnvironment() . '_connections');
                $repeater->label('Current Connections');

                $repeater->allowAdd(false);
                $repeater->allowReorder(false);
                $repeater->removeRowText('Revoke');
                $repeater->onInit('__meros_integrations_init_connections_repeater');
                $repeater->onRemove('__meros_integrations_revoke_connection');

                $repeater->field('hidden', function ($field) {
                    $field->name('connection_id');
                });

                $repeater->field('hidden', function ($field) {
                    $field->name('connection_integration_id');
                });

                $repeater->field('text', function ($field) {
                    $field->name('connection_label');
                    $field->label('Connection Label');
                    $field->readonly(true);
                });

                $repeater->field('text', function ($field) {
                    $field->name('connection_status');
                    $field->label('Status');
                    $field->readonly(true);
                });

                $repeater->field('text', function ($field) {
                    $field->name('connection_environment');
                    $field->label('Environment');
                    $field->readonly(true);
                });

                $repeater->field('text', function ($field) {
                    $field->name('connection_connected_by');
                    $field->label('Connected By');
                    $field->readonly(true);
                });

                $repeater->field('text', function ($field) {
                    $field->name('connection_last_used_at');
                    $field->label('Last Used At');
                    $field->readonly(true);
                });

                $repeater->field('text', function ($field) {
                    $field->name('connection_connected_at');
                    $field->label('Connected At');
                    $field->readonly(true);
                });

                $repeater->field('hidden', function ($field) {
                    $field->name('connection_revoke_nonce');
                });

                $repeater->default($repeaterValue);
            });

        return $repeater->html();
    }

    // ===================================================================================
    // Attribute Setters
    // ===================================================================================

    /**
     * Sets whether multiple connections are allowed per environment for this integration.
     *
     * @param boolean $allow
     *
     * @return void
     */
    final public function allowMultipleConnections(bool $allow = true): void {
        $this->multipleConnectionsPerEnvironment = $allow;
    }

    /**
     * Sets whether connections for this integration are shared across all users or specific to the current user.
     *
     * @param string $scope Either 'all_users' or 'current_user'
     *
     * @return void
     */
    final public function audience(string $scope): void {
        if (!in_array($scope, ['all_users', 'current_user'])) {
            return;
        }

        $this->audience = $scope;
    }

    /**
     * Returns whether connections for this integrations are available for the given scope.
     * If no scope is provided, the method will return the integration's audience.
     *
     * @return string|bool
     */
    final public function connectionsAreFor(?string $scope = null): string|bool {
        if ($scope === null) {
            return $this->audience;
        }

        return $this->audience === $scope;
    }

    // ===================================================================================
    // Authorisation / Initial Token Request Flows
    // ===================================================================================

    /**
     * Returns the integration's authorisation URL.
     * Should be handled by the requester to redirect to the service's authorisation flow.
     *
     * @return string
     */
    final public function getAuthorisationUrl(): string {
        if ($this->canConnect() === false) {
            return '';
        }

        return $this->buildRequestUrl(
            $this->getAuthorizationUrl(),
            $this->getAuthorizationParams()
        );
    }

    /**
     * Returns the application's authorization URL. In most cases, this method needs to be
     * overidden by implementing classes to provide the absolute URL needed to begin the authorization process
     * with their application.
     *
     * @return string
     */
    protected function getAuthorizationUrl(): string {
        return '{base_url}/oauth/authorize';
    }

    /**
     * Returns an array of parameters to be sent with the application's authorization URL as part of the authorization flow.
     * In most cases, this method needs to be overidden by implementing classes to provide the correct
     * paramaters for their application.
     *
     * @return array
     */
    protected function getAuthorizationParams(): array {
        return [
            'client_id'     => '{client_id}',
            'redirect_uri'  => '{return_url}',
            'response_type' => 'code'
        ];
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

        $params = Context::params();
        $error  = $this->getAuthorizationError($params);

        if (is_string($error)) {
            $this->logError($error);
            $this->redirect('error', $error);
        }

        if (is_array($error) && !empty($error)) {
            $this->logError($error[0], $error[1] ?? '', (string) $error[2] ?? null);
            $this->redirect('error', $error[0]);
        }

        $code = $this->getAuthorizationCode($params);

        if ($code === null) {
            $error = 'Authorization code not found in authorization response.';
            $this->logError($error);
            $this->redirect('error', $error);
        }

        // Exchange the authorisation code for an access token
        $response = $this->exchangeCodeForToken($code);

        // Store the token response 
        $this->storeToken($response);

        // Redirect to a success page or back to the integration settings
        $this->redirect('success');
    }

    /**
     * Returns the authorization code provided by the connected application as part of the authorization flow.
     * In most cases, this method needs to be overidden by implementing classes to return the correct parameter from
     * the authorization response.
     *
     * @param array $authorizationResponse
     *
     * @return string|null
     */
    protected function getAuthorizationCode(array $authorizationResponse): ?string {
        if (!is_string($authorizationResponse['code'] ?? null)) {
            return null;
        }

        return $authorizationResponse['code'];
    }

    /**
     * Looks for and returns an authorization error based on the given response provided by the connected application as part of the authorization flow.
     * In most cases, this method needs to be overidden by implementing classes to scan and return an error based on the structure of the response.
     * 
     * Implementing classes may also overide the $authorizationKeys property of this contract if their application returns multiple error-related parameters.
     * This method will use them to construct an array which is then used to log an integration error and redirect the user back to the integration's settings page.
     *
     * @param array $authorizationResponse
     *
     * @return string|array|null
     */
    protected function getAuthorizationError(array $authorizationResponse): string|array|null {
        $error = [];

        foreach ($this->authorizationErrorKeys as $key) {
            if (is_string($authorizationResponse[$key] ?? null)) {
                $error[] = $authorizationResponse[$key];
            }
        }

        if (count($error) > 1) {
            return $error;
        }

        if (count($error) === 1) {
            return reset($error);
        }

        return null;
    }

    /**
     * Exchanges an authorisation code passed from the authorisation callback for an access token.
     *
     * @param string $code
     *
     * @return array
     */
    private function exchangeCodeForToken(string $code): array {
        // Get the token endpoint url
        $endpoint = $this->buildRequestUrl($this->getTokenRequestEndpoint());

        // Get the headers for the request
        $headers = $this->getTokenRequestHeaders();

        // Get the method
        $method = $this->getTokenRequestMethod();
        if (!in_array($method, ['POST', 'GET'])) {
            $error = 'Invalid HTTP method for token request';
            $this->logError($error);
            $this->redirect('error', $error);
        }

        // Build the request payload
        $payload = $this->buildRequestPayload($this->getTokenRequestPayload(), [
            'code' => $code
        ]);

        $response = $this->httpClient->send([
            'method'  => $method,
            'url'     => $endpoint,
            'headers' => $headers,
            'payload' => $payload,
            'format'  => $payload['format'] ?? 'form'
        ]);

        if (!$response->successful()) {
            $error = 'Failed to exchange authorization code for token. HTTP Status: ' . $response->status();
            $this->logError($error);
            $this->redirect('error', $error);
        }

        $formatted = $this->formatResponseBody($response->body(), $this->tokenResponseFormat);
        return $this->parseTokenResponse($formatted);
    }

    /**
     * Returns the application's token request endpoint. In most cases, this method needs to be
     * overidden by implementing classes to provide the absolute URL needed to request a token from their application.
     *
     * @return string
     */
    protected function getTokenRequestEndpoint(): string {
        return '{base_url}/oauth/token';
    }

    /**
     * Returns an array of headers to be sent as part of the token request.
     *
     * @return array
     */
    protected function getTokenRequestHeaders(): array {
        return [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
    }

    /**
     * Returns the HTTP method used to request a token. This method should return either 'POST' or 'GET'.
     *
     * @return string
     */
    protected function getTokenRequestMethod(): string {
        return 'POST';
    }

    /**
     * Returns the payload to be sent with a token request. In most cases, this method needs to be overidden
     * by implementing classes to provide the necessary key => value pairs to be sent with the request.
     *
     * @return array
     */
    protected function getTokenRequestPayload(): array {
        $payload =  [
            'grant_type'   => 'authorization_code',
            'redirect_uri' => '{return_url}'
        ];

        if ($this->resolveConfigurableSettingMethod('clientId') !== null) {
            $payload['client_id'] = '{client_id}';
        }

        if ($this->resolveConfigurableSettingMethod('clientSecret') !== null) {
            $payload['client_secret'] = '{client_secret}';
        }

        return $payload;
    }

    /**
     * Parses a token request repsonse and returns the sanitized array for storage in the External Connections table.
     * This method must return an access token keyed by 'access_token' in the array.
     *
     * @param array $response
     *
     * @return array
     */
    protected function parseTokenResponse(array $response): array {
        return [
            'access_token'  => $response['access_token'] ?? null,
            'id_token'      => $response['id_token'] ?? null,
            'refresh_token' => $response['refresh_token'] ?? null,
            'issued_at'     => $response['issued_at'] ?? null,
            'expires_at'    => $response['expires_at'] ?? null,
            'scope'         => $response['scope'] ?? null
        ];
    }

    /**
     * Stores a token response, and other relevant integration data, as an entry in the
     * External Connections table.
     *
     * @param array  $response
     *
     * @return void
     */
    private function storeToken(array $response): void {
        $accessToken  = $response['access_token'] ?? null;
        $idToken      = $response['id_token'] ?? null;
        $refreshToken = $response['refresh_token'] ?? null;
        $issuedAt     = $response['issued_at'] ?? null;
        $expiresAt    = $response['expires_at'] ?? null;
        $scopes       = $response['scope'] ?? null;

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
                'metadata'         => array_filter($response, function ($key) {
                    return !in_array($key, [
                        'access_token',
                        'refresh_token',
                        'id_token',
                        'scope',
                        'issued_at',
                        'expires_at'
                    ], true);
                }, ARRAY_FILTER_USE_KEY),
            ]);
        } else {
            $error = 'Access token not found in the token response.';
            $this->logError($error);
            $this->redirect('error', $error);
        }
    }

    // ===================================================================================
    // Token Refresh Flow
    // ===================================================================================

    /**
     * Determines whether the access token should be refreshed based on the provided authentication response.
     *
     * @param array $endpointResponse
     *
     * @return boolean
     */
    protected function shouldRefreshToken(array $endpointResponse): bool {
        // Should be implemented by the specific integration if token refresh is supported.
        return false;
    }

    // ===================================================================================
    // Token Revoke Flow
    // ===================================================================================

    // ===================================================================================
    // Request Building
    // ===================================================================================

    protected function getAccessToken(): ?string {
        $connection = $this->getConnection();
        if (is_array($connection) && isset($connection['access_token'])) {
            return $connection['access_token'];
        }

        return null;
    }

    /**
     * Builds a request url with the provided endpoint and optional additional query parameters.
     *
     * @param string $endpoint
     * @param array  $queryParams
     *
     * @return string
     */
    final protected function buildRequestUrl(string $endpoint, array $queryParams = []): string {
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

        return array_merge($payload, $overrides);
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
     * Parses a HTTP request response body and returns it as an array.
     *
     * @param string $body
     * @param string $format
     *
     * @return array
     */
    protected function formatResponseBody(string $body, string $format): array {
        if ($body === '') {
            return [];
        }

        if ($format === 'form') {
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

    /**
     * Helper for redirecting post-Oauth events.
     *
     * @param string $status
     * @param string $message
     *
     * @return void
     */
    private function redirect(string $status, string $message = '') {
        $params = '&status=' . $status;
        if ($message !== '') {
            $params .= '&message=' . urlencode($message);
        }

        wp_redirect(admin_url('options-general.php?page=meros-integrations&integration=' . $this->getName('slug') . $params));
        exit;
    }

    /**
     * Determines whether the current user can connect to this integration based on the connection scope and existing connections.
     *
     * @return boolean
     */
    private function canConnect(): bool {
        if (!current_user_can('manage_options')) {
            return false;
        }

        if ($this->audience === 'current_user') {
            if ($this->multipleConnectionsPerEnvironment === false) {
                $existingConnection = collect($this->connections)
                    ->where('user_id', get_current_user_id())
                    ->where('status', 'connected')
                    ->first();

                return $existingConnection === null;
            }
        }

        else if ($this->audience === 'all_users') {
            if ($this->multipleConnectionsPerEnvironment === false) {
                return $this->connections === [];
            }
        }

        return true;
    }

    /**
     * Returns the current connection (if any) for this integration based on the audience and existing connections.
     *
     * @return array|null
     */
    private function getConnection(): ?array {
        if ($this->audience === 'current_user') {
            return collect($this->connections)
                ->where('user_id', get_current_user_id())
                ->where('status', 'connected')
                ->first();
        }

        else if ($this->audience === 'all_users') {
            return collect($this->connections)
                ->where('status', 'connected')
                ->first();
        }

        return null;
    }
}