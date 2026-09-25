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
    // Configuration for the integration's connections
    // ===================================================================================

    /**
     * Whether the integration should use PKCE (RFC 7636).
     *
     * @var boolean
     */
    private bool $usesPKCE = false;

    /**
     * Whether the integration schedules a heartbeat to keep access tokens valid.
     *
     * @var boolean
     */
    private bool $usesRefreshHeartbeat = false;

    /**
     * The age a token should be (in hours) before a heartbeat will attempt to refresh it.
     *
     * @var integer
     */
    private int $heartbeatTokenAgeHours = 120;

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

    final protected function whenConfigured(): void {
        parent::whenConfigured();
        $this->initHeartbeat();
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

            $authUrl = $this->startAuthFlow();
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

            if (in_array($connection->status, ['revoked', 'expired'], true)) {
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
        $heartbeat     = $this->usesRefreshHeartbeat;
        $maxAgeHours   = $this->heartbeatTokenAgeHours;
        $repeaterValue = [];

        foreach ($this->connections as $connection) {
            $row = [
                'connection_id'             => $connection['id'],
                'connection_integration_id' => $connection['integration_id'],
                'connection_label'          => $connection['label'],
                'connection_environment'    => $connection['environment'],
                'connection_status'         => $connection['status'] !== 'error' ? $connection['status'] : 'Error: ' . $connection['last_error'],
                'connection_connected_by'   => $connection['user_id'] ? get_userdata($connection['user_id'])->user_login : 'Unknown',
                'connection_last_used_at'   => $connection['last_used_at'] ? Carbon::parse($connection['last_used_at'])->toDateTimeString() : null,
                'connection_connected_at'   => $connection['connected_at'] ? Carbon::parse($connection['connected_at'])->toDateTimeString() : null,
                'connection_revoke_nonce'   => wp_create_nonce('meros_integration_revoke_connection_' . $connection['integration_id'])
            ];

            if ($heartbeat) {
                $issuedAt = $connection['token_issued_at']
                        ? Carbon::parse($connection['token_issued_at'])
                        : null;

                $row['connection_last_renewed_at'] = $issuedAt?->toDateTimeString();

                $row['connection_next_renewal'] = ($issuedAt && $connection['status'] === 'connected')
                        ? $issuedAt->copy()->addHours($maxAgeHours)->toDateTimeString()
                        : 'Not scheduled';
            }

            $repeaterValue[] = $row;
        }

        $repeater = Fields::checkout($this->getProvider())
            ->makeFrom('repeater', function (Repeater $repeater) use ($repeaterValue, $heartbeat) {
                $repeater->name($this->getName() . '_' . $this->getCurrentEnvironment() . '_connections');
                $repeater->label('Current Connections');

                $repeater->allowAdd(false);
                $repeater->allowReorder(false);
                $repeater->removeRowText('Disconnect');
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

                if ($heartbeat) {
                    $repeater->field('text', function ($field) {
                        $field->name('connection_last_renewed_at');
                        $field->label('Last Renewed');
                        $field->readonly(true);
                    });

                    $repeater->field('text', function ($field) {
                        $field->name('connection_next_renewal');
                        $field->label('Next Renewal');
                        $field->readonly(true);
                    });
                }

                $repeater->default($repeaterValue);
            });

        return $repeater->html();
    }

    // ===================================================================================
    // Attribute Setters
    // ===================================================================================

    /**
     * Sets the integration to use PKCE (Proof Key for Code Exchange)
     *
     * @param bool $use
     *
     * @return void
     */
    final public function usePKCE($use = true): void {
        $this->usesPKCE = $use;
    }

    /**
     * Sets the integration to schedule a heartbeat to keep access tokens valid via the 
     * refersh token flow.
     *
     * @param int|null $tokenAgeInHours
     *
     * @return void
     */
    final public function useRefreshHeartbeat(?int $tokenAgeInHours = null): void {
        $this->usesRefreshHeartbeat = true;

        if ($tokenAgeInHours !== null) {
            $this->heartbeatTokenAgeHours = $tokenAgeInHours;
        }
    }

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
    // PKCE Configuration
    // ===================================================================================
    
    /**
     * Returns the transient key for storing the PKCE code verifier.
     *
     * @return string
     */
    protected function getPkceTransientKey(): string {
        return 'meros_pkce_' . $this->getName() . '_' . get_current_user_id();
    }

    /**
     * Generates or retrieves the PKCE code verifier for this connection attempt.
     * Verifier survives across the authorization round-trip (start → callback).
     *
     * @return string
     */
    protected function getPkceCodeVerifier(): string {
        $verifier = get_transient($this->getPkceTransientKey());

        if (!is_string($verifier) || $verifier === '') {
            // RFC 7636: 43-128 unreserved characters [A-Z a-z 0-9 - _ . ~]
            $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            set_transient($this->getPkceTransientKey(), $verifier, 10 * MINUTE_IN_SECONDS);
        }

        return $verifier;
    }

    /**
     * Generates the PKCE code challenge from the verifier using S256 (SHA256).
     *
     * @return string
     */
    protected function getPkceCodeChallenge(): string {
        $verifier = $this->getPkceCodeVerifier();
        $hash     = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    /**
     * Clears the stored PKCE code verifier after successful token exchange.
     */
    protected function burnPkceCodeVerifier(): void {
        delete_transient($this->getPkceTransientKey());
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
    final public function startAuthFlow(): string {
        if ($this->canConnect() === false) {
            return '';
        }

        $params = $this->getAuthorizationParams();

        // Append PKCE params if enabled
        if ($this->usesPKCE) {
            $params['code_challenge']        = $this->getPkceCodeChallenge();
            $params['code_challenge_method'] = 'S256';
        }

        return $this->buildRequestUrl(
            $this->getAuthorizationUrl(),
            $params
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

        // Build overrides — include PKCE verifier if enabled
        $overrides = ['code' => $code];

        if ($this->usesPKCE) {
            $overrides['code_verifier'] = $this->getPkceCodeVerifier();
            // Single-use: burn the verifier after consumption
            $this->burnPkceCodeVerifier();
        }

        // Build the request payload
        $payload = $this->buildRequestPayload($this->getTokenRequestPayload(), $overrides);

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

        $formatted = $this->formatResponseBody($response->body(), $this->getTokenResponseFormat());
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
     * Returns the HTTP method used to request a token. This method should return either 'POST' or 'GET'.
     *
     * @return string
     */
    protected function getTokenRequestMethod(): string {
        return 'POST';
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
     * Returns the expected format of a token request response.
     *
     * @return string
     */
    protected function getTokenResponseFormat(): string {
        return 'json';
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
            ExternalConnection::updateOrCreate(
                [
                    'integration_id' => $this->getName(),
                    'environment'    => $this->getCurrentEnvironment(),
                    'user_id'        => get_current_user_id(),
                ],
                [
                    'label'            => $this->getLabel() . ' ' . now()->format('Y-m-d H:i:s'),
                    'is_active'        => true,
                    'access_token'     => $accessToken,
                    'refresh_token'    => $refreshToken,
                    'id_token'         => $idToken,
                    'scopes'           => $scopes,
                    'token_issued_at'  => $this->resolveTimestamp($issuedAt),
                    'token_expires_at' => $this->resolveTimestamp($expiresAt),
                    'last_used_at'     => now(),
                    'connected_at'     => $this->getConnection()['connected_at'] ?? now(), // preserve original connected_at if updating
                    'status'           => 'connected',
                    'status_reason'    => 'Successfully connected via OAuth.',
                    'metadata'         => array_filter($response, function ($key) {
                        return !in_array($key, [
                            'access_token',
                            'refresh_token',
                            'id_token',
                            'scope',
                            'issued_at',
                            'expires_at',
                        ], true);
                    }, ARRAY_FILTER_USE_KEY),
                ]
            );

            // Invalidate local cache so subsequent requests in this cycle see the new token
            $this->initConnections();
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
     * Retrieves the refresh token from the current connection if it exists.
     *
     * @return string|null
     */
    protected function getRefreshToken(): ?string {
        $connection = $this->getConnection();
        if (is_array($connection) && isset($connection['refresh_token'])) {
            return $connection['refresh_token'];
        }

        return null;
    }

    /**
     * Determines whether the access token should be refreshed based on the provided error.
     *
     * @param string $errorTitle
     * @param string $errorMessage
     * @param int|null $errorCode
     *
     * @return boolean
     */
    protected function shouldRefreshToken(string $errorTitle, string $errorMessage = '', ?int $errorCode = null): bool {
        // Should be implemented by the specific integration if token refresh is supported.
        return false;
    }

    /**
     * Attempts to refresh the integration's access token using the current connection's refresh token if it exists.
     *
     * @return string|null
     */
    protected function refreshToken(): ?string {
        $connection   = $this->getConnection();
        $refreshToken = $this->getRefreshToken();

        if (!is_array($connection) || $refreshToken === null) {
            $this->logError('Cannot refresh access token: no refresh token is stored for this connection.');
            return null;
        }

        // Get the refresh token endpoint url
        $endpoint = $this->buildRequestUrl($this->getRefreshTokenEndpoint());

        // Get the headers for the request
        $headers = $this->getRefreshTokenRequestHeaders();

        // Get the method
        $method = $this->getRefreshTokenRequestMethod();
        if (!in_array($method, ['POST', 'GET'])) {
            $error = 'Invalid HTTP method for refresh token request';
            $this->logError($error);
            return null;
        }

        // Build the request payload. The refresh token comes from the connection
        // record rather than a configurable setting, so it is passed as an override.
        $payload = $this->buildRequestPayload($this->getRefreshTokenRequestPayload(), [
            'refresh_token' => $refreshToken
        ]);

        $response = $this->httpClient->send([
            'method'  => $method,
            'url'     => $endpoint,
            'headers' => $headers,
            'payload' => $payload,
            'format'  => $payload['format'] ?? 'form'
        ]);

        if (!$response->successful()) {
            $body = $this->formatResponseBody($response->body(), $this->getRefreshTokenResponseFormat());
            $tokenIsExpired = $this->refreshTokenIsExpired($body);

            if ($tokenIsExpired) {
                $this->logError('Refresh token is no longer valid: ' . ($body['error_description'] ?? 'unknown reason'));
                $this->updateConnection($connection['id'], [
                    'status'        => 'expired',
                    'status_reason' => 'Re-authorization required (' . ($body['error_description'] ?? 'refresh token expired') . ')',
                ]);
                return null;
            }

            $error = 'Failed to refresh access token. HTTP Status: ' . $response->status();
            $this->logError($error);
            return null;
        }

        $formatted = $this->formatResponseBody($response->body(), $this->getRefreshTokenResponseFormat());
        $parsed    = $this->parseRefreshTokenResponse($formatted, $refreshToken);

        if (($parsed['access_token'] ?? null) === null) {
            $this->logError('Access token not found in the refresh token response.');
            return null;
        }

        // Persist the refreshed token onto the existing connection.
        $this->updateConnection($connection['id'], [
            'access_token'      => $parsed['access_token'],
            'id_token'          => $parsed['id_token'] ?? null,
            'refresh_token'     => $parsed['refresh_token'],
            'token_issued_at'   => $this->resolveTimestamp($parsed['issued_at'] ?? null),
            'token_expires_at'  => $this->resolveTimestamp($parsed['expires_at'] ?? null),
            'last_used_at'      => now(),
            'last_refreshed_at' => now(),
            'status'            => 'connected',
            'status_reason'     => 'Access token refreshed successfully.'
        ]);

        return $parsed['access_token'];
    }

    /**
     * Returns the application's refresh token request endpoint. In most cases, this method needs to be
     * overidden by implementing classes to provide the absolute URL needed to request a refresh token from their application.
     *
     * @return string
     */
    protected function getRefreshTokenEndpoint(): string {
        return $this->getTokenRequestEndpoint();
    }

    /**
     * Returns the HTTP method used to request a new token. This method should return either 'POST' or 'GET'.
     *
     * @return string
     */
    protected function getRefreshTokenRequestMethod(): string {
        return $this->getTokenRequestMethod();
    }

    /**
     * Returns the expected format of a (refresh) token request response.
     *
     * @return string
     */
    protected function getRefreshTokenResponseFormat(): string {
        return $this->getTokenResponseFormat();
    }

    /**
     * Returns an array of headers to be sent as part of the refresh token request.
     *
     * @return array
     */
    protected function getRefreshTokenRequestHeaders(): array {
        return $this->getTokenRequestHeaders();
    }

    /**
     * Returns the payload to be sent with a refresh token request. In most cases, this method needs to be overidden
     * by implementing classes to provide the necessary key => value pairs to be sent with the request.
     *
     * @return array
     */
    protected function getRefreshTokenRequestPayload(): array {
        $payload = [
            'grant_type' => 'refresh_token',
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
     * Parses a refresh token request repsonse.
     * This method must return an refresh token keyed by 'refresh_token' in the array.
     *
     * @param array $response
     *
     * @return array
     */
    protected function parseRefreshTokenResponse(array $response, string $currentRefreshToken): array {
        $parsed = $this->parseTokenResponse($response);

        if ($parsed['refresh_token'] === null) {
            $parsed['refresh_token'] = $currentRefreshToken;
        }

        return $parsed;
    }

    /**
     * Returns whether a refresh token has expired based on the refresh token response body.
     *
     * @param array $responseBody
     *
     * @return boolean
     */
    protected function refreshTokenIsExpired(array $responseBody): bool {
        return ($responseBody['error'] ?? '') === 'invalid_grant';
    }

    /**
     * Initialises a heartbeat to keep refresh tokens valid, if opted-into by implementing
     * classes.
     *
     * @return void
     */
    private function initHeartbeat(): void {
        $enabled = $this->usesRefreshHeartbeat;
        if ($enabled === false) return;

        $hook = "meros_integration_heartbeat_{$this->getName()}";

        add_action($hook, function (int $maxAge = 0) {
            $connection = $this->getConnection();
            if (!is_array($connection) || $connection['is_active'] === false) {
                return;
            }

            if ($connection['status'] !== 'connected') {
                return;
            }

            // Prefer the arg the event was scheduled with, falling back to the
            // current method value for robustness (e.g. manually fired via do_action).
            $maxAge = $maxAge > 0 ? $maxAge : $this->heartbeatTokenAgeHours;
            Log::info('testing heartbeat age: ' . $maxAge);

            $issuedAt = $connection['token_issued_at'] ? Carbon::parse($connection['token_issued_at']) : null;

            if ($issuedAt === null || $issuedAt->diffInHours(now()) >= $maxAge) {
                $this->refreshToken();
            }
        });

        add_action('init', function () use ($hook) {
            $maxAge = $this->heartbeatTokenAgeHours;

            // Look for an event for this hook using the CURRENT max-age.
            $scheduled = wp_next_scheduled($hook, [$maxAge]);

            if ($scheduled === false) {
                // Either never scheduled, or scheduled with a DIFFERENT max-age —
                // clear any stale event and (re)schedule with the current value.
                wp_clear_scheduled_hook($hook);
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', $hook, [$maxAge]);
            }
        });
    }
    
    // ===================================================================================
    // Request Building
    // ===================================================================================

    /**
     * Retrieves the access token from the current connection if it exists.
     *
     * @return string|null
     */
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

    protected function logError(string $title, string $message = '', ?int $code = null): void {
        parent::logError($title, $message, $code);

        $connection = $this->getConnection();
        if (is_array($connection)) {
            $this->updateConnection($connection['id'], [
                'status'        => 'error',
                'status_reason' => 'An error occured',
                'last_error'    => $title,
                'last_error_at' => now()->toString()
            ]);
        }
    }

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
                $activeConnections = collect($this->connections)
                    ->whereIn('status', ['connected'])
                    ->count();
                return $activeConnections === 0;
            }
        }

        return true;
    }

    /**
     * Returns the current connection (if any) for this integration based on the audience and existing connections.
     *
     * @return array|null
     */
    protected function getConnection(): ?array {
        if ($this->audience === 'current_user') {
            return collect($this->connections)
                ->where('user_id', get_current_user_id())
                ->first();
        }

        else if ($this->audience === 'all_users') {
            return collect($this->connections)
                ->first();
        }

        return null;
    }

    /**
     * Updates a connection model with the given id.
     *
     * @param integer $id
     * @param array   $data
     *
     * @return void
     */
    protected function updateConnection(int $id, array $data): void {
        $connection = ExternalConnection::find($id);

        if (!($connection instanceof ExternalConnection)) {
            return;
        }
        
        foreach ($data as $key => $value) {
            $connection->{$key} = $value;
        }

        $connection->save();

       foreach ($this->connections as $key => $cached) {
            if ($cached['id'] === $connection->id) {
                $this->connections[$key] = $connection->toArray();
                break;
            }
       }
    }
}