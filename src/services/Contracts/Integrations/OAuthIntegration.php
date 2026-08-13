<?php

namespace MM\Meros\Services\Contracts\Integrations;

use Illuminate\Support\Facades\Log;

use MM\Meros\App\Models\ExternalConnection;

use MM\Meros\Services\Contracts\Integration;
use MM\Meros\Services\Contracts\FeatureProvider;

use MM\Meros\Facades\Context;

abstract class OAuthIntegration extends Integration {
    final protected string $authType = 'oauth';
    final protected string $oauthCallbackURL = '';

    protected string $baseUriFieldName = 'base_uri';
    protected string $baseUriFieldLabel = 'Base URI';
    protected string $baseUriFieldDescription = 'Set the base URI provided by the application.';
    protected string $baseUriFieldPlaceholder = 'e.g. https://api.example.com';
    protected string $baseUriFieldDefault = 'https://example.com';

    protected string $clientIdFieldName = 'client_id';
    protected string $clientIdFieldLabel = 'Client ID';
    protected string $clientIdFieldDescription = 'Set the client ID provided by the application to authorize the integration.';
    protected string $clientIdFieldPlaceholder = 'Enter your OAuth client ID';
    protected string $clientIdFieldDefault = '';

    protected string $clientSecretFieldName = 'client_secret';
    protected string $clientSecretFieldLabel = 'Client Secret';
    protected string $clientSecretFieldDescription = 'Set the client secret provided by the application to authorize the integration.';
    protected string $clientSecretFieldPlaceholder = 'Enter your OAuth client secret';
    protected string $clientSecretFieldDefault = '';

    protected string $returnUrlFieldName = 'return_url';
    protected string $returnUrlFieldLabel = 'Return URL';
    protected string $returnUrlFieldDescription = 'The URL you are redirected to after authorizing the integration. This URL should typically be set in the application\'s OAuth settings.';

    protected string $authorizationEndpoint = '{base_uri}/oauth/{api_version}/authorize';
    protected string $tokenEndpoint = '{base_uri}/oauth/{api_version}/token';
    protected string $tokenRefreshEndpoint = '{base_uri}/oauth/{api_version}/token/refresh';
    protected string $tokenRevokeEndpoint = '{base_uri}/oauth/{api_version}/token/revoke';

    protected string $callbackResponseType = 'query'; // or 'fragment'
    protected string $callbackErrorResponseType = 'query'; // or 'fragment'

    protected string $callbackCodeKey = 'code';
    protected string $callbackStateKey = 'state';
    protected string $callbackErrorKey = 'error';
    protected string $callbackErrorDescriptionKey = 'error_description';

    protected string $tokenRequestType = 'form'; // or 'json'
    protected string $tokenRefreshRequestType = 'form'; // or 'json'
    protected string $tokenRevokeRequestType = 'form'; // or 'json'

    protected string $tokenRequestGrantType = 'authorization_code';
    protected string $tokenRefreshRequestGrantType = 'refresh_token';
    protected string $tokenRevokeRequestGrantType = 'revoke_token';
    
    protected string $tokenRequestContentType = 'application/x-www-form-urlencoded'; // or 'application/json'
    protected string $tokenRefreshRequestContentType = 'application/x-www-form-urlencoded'; // or 'application/json'
    
    protected string $tokenRevokeRequestContentType = 'application/x-www-form-urlencoded'; // or 'application/json'
    protected string $tokenRequestAcceptType = 'application/json'; // or 'application/x-www-form-urlencoded'
    protected string $tokenRefreshRequestAcceptType = 'application/json'; // or 'application/x-www-form-urlencoded'
    protected string $tokenRevokeRequestAcceptType = 'application/json'; // or 'application/x-www-form-urlencoded'
    
    protected string $tokenRequestMethod = 'POST'; // or 'GET'
    protected string $tokenRefreshRequestMethod = 'POST'; // or 'GET'
    protected string $tokenRevokeRequestMethod = 'POST'; // or 'GET'

    protected array $authorisationQueryParams = [];
    
    protected array $tokenRequestPayloadSchema = [
        'grant_type'    => '{token_request_grant_type}',
        'code'          => 'code',
        'redirect_uri'  => '{return_url}',
        'client_id'     => '{client_id}',
        'client_secret' => '{client_secret}',
    ];

    protected array $tokenRefreshPayloadSchema = [
        'grant_type'    => '{token_refresh_request_grant_type}',
        'refresh_token' => '{refresh_token}',
        'client_id'     => '{client_id}',
        'client_secret' => '{client_secret}',
    ];

    protected array $tokenRevokePayloadSchema = [
        'grant_type'    => '{token_revoke_request_grant_type}',
        'token'         => '{access_token}',
        'client_id'     => '{client_id}',
        'client_secret' => '{client_secret}',
    ];
    
    protected string $tokenResponseType = 'json'; // or 'form'
    protected string $tokenRefreshResponseType = 'json'; // or 'form'

    protected string $tokenType = 'Bearer'; // or 'MAC', 'Basic', etc.
    protected string $accessTokenKey = 'access_token';
    protected string $refreshTokenKey = 'refresh_token';
    protected string $idTokenKey = 'id_token';

    protected string $tokenIssuedAtKey = 'issued_at';
    protected string $tokenExpiresAtKey = 'expires_at';
    protected string $tokenExpiresInKey = 'expires_in';

    protected string $tokenScopeKey = 'scope';
    protected string $tokenErrorKey = 'error';
    protected string $tokenErrorDescriptionKey = 'error_description';

    protected ?ExternalConnection $currentConnection = null;

    // =========================================================================
    // Initialisation
    // =========================================================================

    public function __construct(FeatureProvider $provider, array $props = []) {
        $this->initRequiredProperties();

        $this->oauthCallbackURL = admin_url('admin-post.php?action=meros_integration_oauth_callback_' . $this->getHandle(true));
        
        add_action('admin_post_meros_integration_oauth_callback_' . $this->getHandle(true), [$this, 'handleOAuthCallback']);
        
        parent::__construct($provider, $props);
    }

    final protected function initConfigurationFields(): void {
        $defaultFields = [
            [
                'type'        => 'string',
                'field_type'  => 'url',
                'name'        => $this->baseUriFieldName,
                'label'       => $this->baseUriFieldLabel,
                'placeholder' => $this->baseUriFieldPlaceholder,
                'description' => $this->baseUriFieldDescription,
                'default'     => $this->baseUriFieldDefault,
                'required'    => true,
            ],
            [
                'type'        => 'string',
                'name'        => $this->clientIdFieldName,
                'label'       => $this->clientIdFieldLabel,
                'placeholder' => $this->clientIdFieldPlaceholder,
                'description' => $this->clientIdFieldDescription,
                'default'     => $this->clientIdFieldDefault,
                'required'    => true,
            ],
            [
                'type'        => 'string',
                'field_type'  => 'password',
                'name'        => $this->clientSecretFieldName,
                'label'       => $this->clientSecretFieldLabel,
                'placeholder' => $this->clientSecretFieldPlaceholder,
                'description' => $this->clientSecretFieldDescription,
                'default'     => $this->clientSecretFieldDefault,
                'required'    => true,
                'encrypt'     => true,
            ],
            [
                'type'        => 'string',
                'field_type'  => 'url',
                'name'        => $this->returnUrlFieldName,
                'label'       => $this->returnUrlFieldLabel,
                'readonly'    => true,
                'description' => $this->returnUrlFieldDescription,
                'default'     => $this->oauthCallbackURL,
            ]
        ];

        if ($this->mergeFields) {
            $this->fields = array_merge($defaultFields, $this->fields);
        } else {
            $this->fields = $this->fields;
        }
    }

    // =========================================================================
    // OAuth Setup Flow
    // =========================================================================

    public function connect(): string {
        return $this->getAuthorisationUrl();
    }

    public function handleOAuthCallback(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access.');
        }

        $params = Context::params();

        $error = $params[$this->callbackErrorKey] ?? null;
        $errorDescription = $params[$this->callbackErrorDescriptionKey] ?? null;

        if ($error !== null) {
            $errorMessage = $errorDescription ?? 'An error occurred during the OAuth callback.';
            wp_redirect($this->integrationPageUrl . '&status=error&description=' . urlencode($errorMessage));
            exit;
        }

        $code = $params[$this->callbackCodeKey] ?? null;

        if (!$code) {
            wp_redirect($this->integrationPageUrl . '&status=error&description=' . urlencode('Authorization code not provided.'));
            exit;
        }

        // Exchange the authorization code for an access token
        $tokenResponse = $this->exchangeAuthorizationCodeForToken($code);

        // Handle the token response (e.g., save it to the database)
        $this->handleTokenResponse($tokenResponse);

        // Redirect to a success page or back to the integration settings
        wp_redirect(admin_url('options-general.php?page=meros-integrations&integration=' . $this->getHandle() . '&status=success'));
        exit;
    }

    protected function getAuthorisationUrl(): string {
        return $this->buildRequestUrl(
            $this->authorizationEndpoint,
            $this->authorisationQueryParams
        );
    }

    protected function exchangeAuthorizationCodeForToken(string $code): array {
        // Build the token endpoint URL
        $tokenEndpoint = $this->buildRequestUrl($this->tokenEndpoint);

        // Build the headers for the token request
        $headers = $this->buildTokenRequestHeaders($this->tokenRequestAcceptType, $this->tokenRequestContentType);

        // Build the request payload
        $payload = $this->buildRequestPayload($this->tokenRequestPayloadSchema, [
            'code' => $code,
        ]);

        // Make the request to exchange the authorization code for an access token
        $response = $this->httpClient->send([
            'method'  => $this->tokenRequestMethod,
            'url'     => $tokenEndpoint,
            'headers' => $headers,
            'payload' => $payload,
            'format'  => $this->tokenRequestType,
        ]);

        if (!$response->successful()) {
            wp_redirect($this->integrationPageUrl . '&status=error&message=' . urlencode('Failed to exchange authorization code for token. HTTP Status: ' . $response->status()));
            exit;
        }

        return $this->parseTokenResponseBody($response->body(), $this->tokenResponseType);
    }

    protected function handleTokenResponse(array $tokenResponse): void {
        if (isset($tokenResponse[$this->accessTokenKey])) {
            ExternalConnection::updateOrCreate([
                'label'            => $this->getLabel() . ' ' . now()->format('Y-m-d H:i:s'),
                'integration_id'   => $this->getHandle(),
                'environment'      => $this->getCurrentEnvironment(),
                'user_id'          => get_current_user_id(),
                'is_active'        => true,
                'access_token'     => $tokenResponse[$this->accessTokenKey],
                'refresh_token'    => $tokenResponse[$this->refreshTokenKey] ?? null,
                'id_token'         => $tokenResponse[$this->idTokenKey] ?? null,
                'scopes'           => $tokenResponse[$this->tokenScopeKey] ?? null,
                // 'token_issued_at'  => $this->resolveTokenIssuedAt($tokenResponse),
                'token_expires_at' => $this->resolveTokenExpiresAt($tokenResponse),
                'last_used_at'     => now(),
                'connected_at'     => now(),
                'status'           => 'connected',
                'status_reason'    => 'Successfully connected via OAuth.',
                'metadata'         => array_filter($tokenResponse, function ($key) {
                    return !in_array($key, [
                        $this->accessTokenKey,
                        $this->refreshTokenKey,
                        $this->idTokenKey,
                        $this->tokenScopeKey,
                        $this->tokenIssuedAtKey,
                        $this->tokenExpiresAtKey,
                        $this->tokenExpiresInKey,
                    ], true);
                }, ARRAY_FILTER_USE_KEY),
            ]);
        } else {
            wp_redirect($this->integrationPageUrl . '&status=error&message=' . urlencode('Access token not found in the token response.'));
            exit;
        }
    }

    // =========================================================================
    // OAuth Token Refresh Flow
    // =========================================================================

    /**
     * Checks if the current connection has a refresh token available.
     *
     * @return boolean
     */
    protected function hasRefreshToken(): bool {
        return !empty($this->currentConnection->refresh_token);
    }

    /**
     * Refreshes the access token using the refresh token if available.
     *
     * @return boolean Returns true if the token was successfully refreshed, false otherwise.
     */
    protected function refreshToken(): bool {
        if (!$this->hasRefreshToken()) {
            return false;
        }

        $refreshEndpoint = $this->buildRequestUrl($this->tokenRefreshEndpoint);

        // Prepare the request payload
        $payload = $this->buildRequestPayload($this->tokenRefreshPayloadSchema, [
            'refresh_token' => $this->currentConnection->refresh_token,
        ]);

        // Make the request to refresh the access token
        $response = $this->httpClient->send([
            'method'  => $this->tokenRefreshRequestMethod,
            'url'     => $refreshEndpoint,
            'headers' => $this->buildTokenRequestHeaders($this->tokenRefreshRequestAcceptType, $this->tokenRefreshRequestContentType),
            'payload' => $payload,
            'format'  => $this->tokenRefreshRequestType,
        ]);

        if (!$response->successful()) {
            // Handle the error (e.g., log it, notify the user, etc.)
            return false;
        }

        $tokenResponse = $this->parseTokenResponseBody($response->body(), $this->tokenRefreshResponseType);

        // Update the connection with the new token information
        if (isset($tokenResponse[$this->accessTokenKey])) {
            $this->currentConnection->update([
                'access_token'     => $tokenResponse[$this->accessTokenKey],
                'refresh_token'    => $tokenResponse[$this->refreshTokenKey] ?? null,
                'id_token'         => $tokenResponse[$this->idTokenKey] ?? null,
                'scopes'           => $tokenResponse[$this->tokenScopeKey] ?? null,
                'token_issued_at'  => $this->resolveTokenIssuedAt($tokenResponse) ?? now(),
                'token_expires_at' => $this->resolveTokenExpiresAt($tokenResponse),
                'last_refreshed_at'=> now(),
            ]);
            return true;
        }
        return false;
    }

    // =========================================================================
    // OAuth Token Revoke Flow
    // =========================================================================

    public function revokeConnection(ExternalConnection $connection): bool {
        if (empty($connection->access_token)) {
            return false;
        }

        $revokeEndpoint = $this->buildRequestUrl($this->tokenRevokeEndpoint);

        Log::debug('Revoking OAuth connection for integration: ' . $this->getHandle() . ', connection label: ' . $connection->label, [
            'revoke_endpoint' => $revokeEndpoint,
            'access_token'    => $connection->access_token,
        ]);

        return true;

        // Prepare the request payload
        $payload = $this->buildRequestPayload($this->tokenRevokePayloadSchema, [
            'access_token' => $connection->access_token,
        ]);

        // Make the request to revoke the access token
        $response = $this->httpClient->send([
            'method'  => $this->tokenRevokeRequestMethod,
            'url'     => $revokeEndpoint,
            'headers' => $this->buildTokenRequestHeaders($this->tokenRevokeRequestAcceptType, $this->tokenRevokeRequestContentType),
            'payload' => $payload,
            'format'  => $this->tokenRevokeRequestType,
        ]);

        if (!$response->successful()) {
            // Handle the error (e.g., log it, notify the user, etc.)
            return false;
        }

        // Optionally, you can update the connection status in your database
        $connection->update([
            'status' => 'revoked',
            'status_reason' => 'Access token revoked by user.',
            'access_token' => null,
            'refresh_token' => null,
            'id_token' => null,
            'scopes' => null,
            'token_issued_at' => null,
            'token_expires_at' => null,
        ]);

        return true;
    }

    // =========================================================================
    // OAuth Token Helpers
    // =========================================================================

    /**
     * Builds the headers for token requests based on the specified Accept and Content-Type headers.
     *
     * @param string $acceptType  The Accept header value (e.g., 'application/json').
     * @param string $contentType The Content-Type header value (e.g., 'application/x-www-form-urlencoded').
     * 
     * @return array An associative array of headers for the token request.
     */
    protected function buildTokenRequestHeaders(string $acceptType, string $contentType): array {
        return [
            'Accept'       => $acceptType,
            'Content-Type' => $contentType,
        ];
    }

    /**
     * Parses the token response body based on the expected response type (JSON or form-encoded).
     *
     * @param string $body         The raw response body from the token endpoint.
     * @param string $responseType The expected response type ('json' or 'form').
     * 
     * @return array The parsed token response as an associative array.
     */
    protected function parseTokenResponseBody(string $body, string $responseType): array {
        if ($body === '') {
            return [];
        }

        if ($responseType === 'form') {
            parse_str($body, $parsed);
            return is_array($parsed) ? $parsed : [];
        }

        $parsed = json_decode($body, true);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Resolves the token issued time from the token response. 
     * It checks for both absolute timestamps and relative durations (in seconds).
     * If neither is provided, it returns null.
     *
     * @param array $tokenResponse
     *
     * @return mixed
     */
    protected function resolveTokenIssuedAt(array $tokenResponse): mixed {
        $issuedAt = $tokenResponse[$this->tokenIssuedAtKey] ?? null;

        if ($issuedAt === null || $issuedAt === '') {
            return null;
        }

        if (is_numeric($issuedAt)) {
            return now()->setTimestamp((int) $issuedAt);
        }

        return $issuedAt;
    }

    /**
     * Resolves the token expiration time from the token response. 
     * It checks for both absolute expiration timestamps and relative 
     * expiration durations (in seconds). If neither is provided, it returns null.
     *
     * @param array $tokenResponse
     *
     * @return mixed
     */
    protected function resolveTokenExpiresAt(array $tokenResponse): mixed {
        $expiresAt = $tokenResponse[$this->tokenExpiresAtKey] ?? null;

        if ($expiresAt !== null && $expiresAt !== '') {
            if (is_numeric($expiresAt)) {
                $value = (int) $expiresAt;

                // Heuristic: values larger than year 2000 are likely Unix timestamps.
                if ($value > 946684800) {
                    return now()->setTimestamp($value);
                }

                return now()->addSeconds($value);
            }

            return $expiresAt;
        }

        $expiresIn = $tokenResponse[$this->tokenExpiresInKey] ?? null;

        if (is_numeric($expiresIn)) {
            return now()->addSeconds((int) $expiresIn);
        }

        return null;
    }

    /**
     * Returns whether the current connection has a valid (non-expired) access token.
     *
     * @return boolean
     */
    protected function hasValidToken(): bool {
        if ($this->currentConnection === null) {
            return false;
        }

        $expiresAt = $this->currentConnection->token_expires_at;

        if ($expiresAt === null) {
            return false;
        }

        if (now()->lt($expiresAt)) {
            return true;
        }

        return false;
    }

    // =========================================================================
    // Usage Flows
    // =========================================================================

    /**
     * Get the authorization headers for making authenticated requests to the external service.
     *
     * @return array
     */
    public function getAuthorisationHeaders(): array {
        if ($this->currentConnection === null) {
            return [
                'Accept' => 'application/json',
            ];
        }

        return [
            'Authorization' => $this->tokenType . ' ' . $this->currentConnection->access_token,
            'Accept'        => 'application/json',
        ];
    }
}