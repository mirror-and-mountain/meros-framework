<?php

namespace MM\Meros\Support\Integrations;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use MM\Meros\App\Models\IntegrationAccount;
use MM\Meros\App\Models\IntegrationConnection;
use MM\Meros\App\Models\IntegrationEnvironment;
use MM\Meros\Facades\Integrations;
use MM\Meros\Services\Contracts\Integration as IntegrationDefinition;

final class OAuthManager {
    public function __construct(
        protected OAuthStateStore $stateStore
    ) {
    }

    public function buildAuthorizationRedirect(string $integrationHandle, array $options = []): array {
        $definition = $this->resolveDefinition($integrationHandle);
        $integrationSettings = $this->integrationSettings($integrationHandle);
        $provider = $this->resolveProviderHandle($definition);

        $environment = $this->normalizeEnvironment((string) ($options['environment'] ?? $this->setting($integrationHandle, 'default_environment', 'production')));
        $environmentConfig = $this->resolveEnvironmentConfig($integrationHandle, $provider, $environment, $integrationSettings);

        $clientId = trim((string) $this->setting($integrationHandle, 'client_id', ''));
        $clientSecret = trim((string) $this->setting($integrationHandle, 'client_secret', ''));
        $redirectUri = trim((string) ($options['redirect_uri'] ?? $this->setting($integrationHandle, 'redirect_uri', '')));
        $returnUrl = trim((string) ($options['return_url'] ?? ''));

        if ($redirectUri === '') {
            $redirectUri = $this->callbackUrl();
        }

        if ($returnUrl === '') {
            $returnUrl = $this->integrationsSettingsUrl($integrationHandle);
        }

        if ($clientId === '') {
            throw new \RuntimeException('OAuth configuration is incomplete: missing client_id.');
        }

        if ($environmentConfig['authorize_url'] === '') {
            throw new \RuntimeException('OAuth configuration is incomplete: missing authorize_url for environment ' . $environment . '.');
        }

        if ($environmentConfig['token_url'] === '') {
            throw new \RuntimeException('OAuth configuration is incomplete: missing token_url for environment ' . $environment . '.');
        }

        $this->syncEnvironmentRecord($provider, $integrationHandle, $environment, $environmentConfig);

        $scopes = $this->resolveScopes($integrationHandle, $integrationSettings, $definition, $options['scopes'] ?? null);

        $statePayload = [
            'integration_handle' => $integrationHandle,
            'provider' => $provider,
            'environment' => $environment,
            'account_label' => trim((string) ($options['account_label'] ?? '')),
            'connection_label' => trim((string) ($options['connection_label'] ?? '')),
            'token_url' => $environmentConfig['token_url'],
            'authorize_url' => $environmentConfig['authorize_url'],
            'base_uri' => $environmentConfig['base_uri'],
            'instance_url' => $environmentConfig['instance_url'],
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scopes),
            'return_url' => $returnUrl,
            'reconnect_connection_id' => isset($options['reconnect_connection_id']) ? (int) $options['reconnect_connection_id'] : null,
        ];

        $usePkce = $this->shouldUsePkce($integrationHandle, $options);
        $codeChallenge = '';

        if ($usePkce) {
            $verifier = $this->generateCodeVerifier();
            $statePayload['pkce_verifier'] = $verifier;
            $codeChallenge = $this->codeChallenge($verifier);
        }

        $state = $this->stateStore->issue($statePayload, 10 * 60);

        $query = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ];

        if ($scopes !== []) {
            $query['scope'] = implode(' ', $scopes);
        }

        if ($usePkce) {
            $query['code_challenge'] = $codeChallenge;
            $query['code_challenge_method'] = 'S256';
        }

        $authorizeUrl = $environmentConfig['authorize_url'];
        $separator = str_contains($authorizeUrl, '?') ? '&' : '?';

        return [
            'url' => $authorizeUrl . $separator . http_build_query($query),
            'state' => $state,
            'environment' => $environment,
            'return_url' => $returnUrl,
        ];
    }

    public function handleCallback(array $query): array {
        $state = trim((string) ($query['state'] ?? ''));

        if ($state === '') {
            throw new \RuntimeException('OAuth callback is missing state.');
        }

        $statePayload = $this->stateStore->consume($state);

        if (!is_array($statePayload)) {
            throw new \RuntimeException('OAuth state is invalid or has expired. Please try connecting again.');
        }

        if (!empty($query['error'])) {
            $message = trim((string) ($query['error_description'] ?? $query['error']));
            $this->logWarning('oauth_callback_error', [
                'integration_handle' => $statePayload['integration_handle'] ?? null,
                'environment' => $statePayload['environment'] ?? null,
                'error' => $query['error'] ?? null,
            ]);
            throw new \RuntimeException($message !== '' ? $message : 'OAuth authorization was denied.');
        }

        $code = trim((string) ($query['code'] ?? ''));

        if ($code === '') {
            throw new \RuntimeException('OAuth callback is missing authorization code.');
        }

        $tokenResponse = $this->exchangeAuthorizationCode($statePayload, $code);
        $connection = $this->persistConnection($statePayload, $tokenResponse);

        return [
            'ok' => true,
            'integration_handle' => $connection->account?->integration_handle,
            'connection_id' => $connection->getKey(),
            'return_url' => (string) ($statePayload['return_url'] ?? ''),
        ];
    }

    public function refreshConnectionToken(IntegrationConnection $connection): bool {
        $refreshToken = $connection->secrets()->refreshToken();

        if ($refreshToken === null) {
            $this->markConnectionRefreshFailure($connection, 'Missing refresh token.');
            return false;
        }

        $account = $connection->account;

        if (!$account instanceof IntegrationAccount) {
            $this->markConnectionRefreshFailure($connection, 'Connection is missing account association.');
            return false;
        }

        $integrationHandle = $account->integration_handle;
        $integrationSettings = $this->integrationSettings($integrationHandle);
        $environment = $this->normalizeEnvironment($account->preferredEnvironment() ?? 'production');
        $environmentConfig = $this->resolveEnvironmentConfig($integrationHandle, (string) $account->provider, $environment, $integrationSettings);

        $clientId = trim((string) $this->setting($integrationHandle, 'client_id', ''));
        $clientSecret = trim((string) $this->setting($integrationHandle, 'client_secret', ''));
        $tokenUrl = trim((string) ($connection->secrets()->metadata('token_url') ?? $environmentConfig['token_url'] ?? ''));

        if ($clientId === '' || $tokenUrl === '') {
            $this->markConnectionRefreshFailure($connection, 'Cannot refresh token: token URL or client ID is missing.');
            return false;
        }

        $scope = (string) ($connection->secrets()->metadata('scope') ?? '');

        $requestBody = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
        ];

        if ($clientSecret !== '') {
            $requestBody['client_secret'] = $clientSecret;
        }

        if ($scope !== '') {
            $requestBody['scope'] = $scope;
        }

        $response = Http::asForm()->timeout(20)->post($tokenUrl, $requestBody);

        if ($response->failed()) {
            $this->markConnectionRefreshFailure(
                $connection,
                $this->responseErrorMessage($response->json(), 'Refresh token request failed.')
            );

            $this->logWarning('oauth_refresh_failed', [
                'connection_id' => $connection->getKey(),
                'integration_handle' => $integrationHandle,
                'environment' => $environment,
                'status' => $response->status(),
            ]);

            return false;
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            $this->markConnectionRefreshFailure($connection, 'Refresh token response was not JSON.');
            return false;
        }

        $accessToken = trim((string) ($payload['access_token'] ?? ''));

        if ($accessToken === '') {
            $this->markConnectionRefreshFailure($connection, 'Refresh token response did not include access_token.');
            return false;
        }

        $metadata = $connection->metadata ?? [];

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $metadata = array_merge($metadata, [
            'token_url' => $tokenUrl,
            'environment' => $environment,
            'token_type' => $payload['token_type'] ?? ($metadata['token_type'] ?? 'Bearer'),
            'scope' => $payload['scope'] ?? ($metadata['scope'] ?? ''),
            'instance_url' => $payload['instance_url'] ?? ($metadata['instance_url'] ?? null),
        ]);

        $connection->fill([
            'access_token' => $accessToken,
            'refresh_token' => trim((string) ($payload['refresh_token'] ?? $refreshToken)),
            'id_token' => trim((string) ($payload['id_token'] ?? ($connection->id_token ?? ''))),
            'scopes' => $this->normaliseScopes($payload['scope'] ?? ($metadata['scope'] ?? '')),
            'token_expires_at' => $this->resolveExpiry($payload),
            'is_active' => true,
            'status' => 'active',
            'status_reason' => 'token_refreshed',
            'last_refreshed_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
            'metadata' => $metadata,
        ]);

        $connection->save();

        return true;
    }

    public function disconnectConnection(IntegrationConnection $connection): void {
        $connection->fill([
            'api_key' => null,
            'access_token' => null,
            'refresh_token' => null,
            'id_token' => null,
            'scopes' => null,
            'token_expires_at' => null,
            'is_active' => false,
            'status' => 'disconnected',
            'status_reason' => 'manual_disconnect',
            'revoked_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ]);

        $connection->save();

        $account = $connection->account;

        if ($account instanceof IntegrationAccount) {
            $account->is_active = $account->connections()->where('is_active', true)->exists();
            $account->save();
        }
    }

    public function connectionIsUsable(IntegrationConnection $connection): bool {
        if (!$connection->is_active) {
            return false;
        }

        $status = trim((string) ($connection->status ?? 'active'));

        if ($status !== '' && !in_array($status, ['active', 'connected', 'token_refreshed'], true)) {
            return false;
        }

        $secrets = $connection->secrets();

        if ($secrets->bearerToken() === null) {
            return false;
        }

        if (!$secrets->isExpired()) {
            return true;
        }

        return $secrets->hasRefreshToken();
    }

    private function exchangeAuthorizationCode(array $statePayload, string $code): array {
        $tokenUrl = trim((string) ($statePayload['token_url'] ?? ''));
        $clientId = trim((string) ($statePayload['client_id'] ?? ''));
        $clientSecret = trim((string) ($statePayload['client_secret'] ?? ''));
        $redirectUri = trim((string) ($statePayload['redirect_uri'] ?? ''));
        $pkceVerifier = trim((string) ($statePayload['pkce_verifier'] ?? ''));

        if ($tokenUrl === '' || $clientId === '' || $redirectUri === '') {
            throw new \RuntimeException('OAuth callback is missing token exchange configuration.');
        }

        $requestBody = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ];

        if ($clientSecret !== '') {
            $requestBody['client_secret'] = $clientSecret;
        }

        if ($pkceVerifier !== '') {
            $requestBody['code_verifier'] = $pkceVerifier;
        }

        $response = Http::asForm()->timeout(20)->post($tokenUrl, $requestBody);

        if ($response->failed()) {
            $this->logWarning('oauth_token_exchange_failed', [
                'integration_handle' => $statePayload['integration_handle'] ?? null,
                'environment' => $statePayload['environment'] ?? null,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException($this->responseErrorMessage($response->json(), 'Token exchange failed.'));
        }

        $payload = $response->json();

        if (!is_array($payload)) {
            throw new \RuntimeException('Token exchange response was not valid JSON.');
        }

        if (trim((string) ($payload['access_token'] ?? '')) === '') {
            throw new \RuntimeException('Token exchange response did not include access_token.');
        }

        return $payload;
    }

    private function persistConnection(array $statePayload, array $tokenPayload): IntegrationConnection {
        $integrationHandle = trim((string) ($statePayload['integration_handle'] ?? ''));
        $provider = trim((string) ($statePayload['provider'] ?? 'framework'));
        $environment = $this->normalizeEnvironment((string) ($statePayload['environment'] ?? 'production'));
        $accountLabel = trim((string) ($statePayload['account_label'] ?? ''));
        $connectionLabel = trim((string) ($statePayload['connection_label'] ?? ''));

        if ($integrationHandle === '') {
            throw new \RuntimeException('Cannot persist OAuth connection: missing integration handle.');
        }

        if ($accountLabel === '') {
            $accountLabel = Str::title(str_replace(['-', '_'], ' ', $integrationHandle)) . ' ' . ucfirst($environment);
        }

        if ($connectionLabel === '') {
            $connectionLabel = 'default';
        }

        $definition = $this->resolveDefinition($integrationHandle);

        $account = IntegrationAccount::query()->firstOrNew([
            'provider' => $provider,
            'integration_handle' => $integrationHandle,
            'environment' => $environment,
            'label' => $accountLabel,
        ]);

        $account->fill([
            'category' => $definition?->getCategory() ?? 'general',
            'auth_type' => 'oauth',
            'is_active' => true,
            'settings' => [
                'oauth' => [
                    'environment' => $environment,
                    'authorize_url' => $statePayload['authorize_url'] ?? null,
                    'token_url' => $statePayload['token_url'] ?? null,
                    'base_uri' => $statePayload['base_uri'] ?? null,
                ],
            ],
        ]);

        $account->save();

        $reconnectConnectionId = (int) ($statePayload['reconnect_connection_id'] ?? 0);

        $connection = null;

        if ($reconnectConnectionId > 0) {
            $connection = $account->connections()->whereKey($reconnectConnectionId)->first();
        }

        if (!$connection instanceof IntegrationConnection) {
            $connection = $account->connections()->where('label', $connectionLabel)->first();
        }

        if (!$connection instanceof IntegrationConnection) {
            $connection = new IntegrationConnection();
            $connection->account_id = $account->getKey();
            $connection->label = $connectionLabel;
            $connection->connected_at = now();
        }

        $metadata = $connection->metadata ?? [];

        if (!is_array($metadata)) {
            $metadata = [];
        }

        $metadata = array_merge($metadata, [
            'instance_url' => $tokenPayload['instance_url'] ?? ($statePayload['instance_url'] ?? null),
            'token_type' => $tokenPayload['token_type'] ?? 'Bearer',
            'scope' => $tokenPayload['scope'] ?? ($statePayload['scope'] ?? ''),
            'environment' => $environment,
            'token_url' => $statePayload['token_url'] ?? null,
            'authorize_url' => $statePayload['authorize_url'] ?? null,
            'base_uri' => $statePayload['base_uri'] ?? null,
        ]);

        $connection->fill([
            'account_id' => $account->getKey(),
            'label' => $connectionLabel,
            'access_token' => (string) ($tokenPayload['access_token'] ?? ''),
            'refresh_token' => trim((string) ($tokenPayload['refresh_token'] ?? '')),
            'id_token' => trim((string) ($tokenPayload['id_token'] ?? '')),
            'scopes' => $this->normaliseScopes($tokenPayload['scope'] ?? ($statePayload['scope'] ?? '')),
            'metadata' => $metadata,
            'token_expires_at' => $this->resolveExpiry($tokenPayload),
            'is_active' => true,
            'status' => 'active',
            'status_reason' => 'connected',
            'last_refreshed_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
            'revoked_at' => null,
        ]);

        if (!$connection->connected_at) {
            $connection->connected_at = now();
        }

        $connection->save();

        return $connection;
    }

    private function shouldUsePkce(string $integrationHandle, array $options): bool {
        if (array_key_exists('pkce', $options)) {
            return (bool) $options['pkce'];
        }

        return (bool) $this->setting($integrationHandle, 'oauth_use_pkce', false);
    }

    private function resolveScopes(
        string $integrationHandle,
        array $integrationSettings,
        ?IntegrationDefinition $definition,
        mixed $provided
    ): array {
        if (is_array($provided) && $provided !== []) {
            return array_values(array_unique(array_filter(array_map(fn ($scope) => trim((string) $scope), $provided))));
        }

        $scopeSetting = trim((string) ($this->setting($integrationHandle, 'scopes', '') ?? ''));

        if ($scopeSetting !== '') {
            return $this->normaliseScopes($scopeSetting);
        }

        if ($definition instanceof IntegrationDefinition) {
            return $definition->getScopes();
        }

        $scopes = $integrationSettings['scopes'] ?? [];

        return $this->normaliseScopes($scopes);
    }

    private function resolveEnvironmentConfig(string $integrationHandle, string $provider, string $environment, array $settings): array {
        $environmentRow = IntegrationEnvironment::query()
            ->where('provider', $provider)
            ->where('integration_handle', $integrationHandle)
            ->where('environment', $environment)
            ->first();

        $environmentSettings = $environmentRow instanceof IntegrationEnvironment && is_array($environmentRow->settings)
            ? $environmentRow->settings
            : [];

        $authorizeKey = 'authorize_url_' . $environment;
        $tokenKey = 'token_url_' . $environment;
        $baseUriKey = 'base_uri_' . $environment;
        $instanceUrlKey = 'instance_url_' . $environment;

        $environmentAuthorize = trim((string) (($environmentSettings['authorize_url'] ?? '') ?: $this->setting($integrationHandle, $authorizeKey, '')));
        $environmentToken = trim((string) (($environmentSettings['token_url'] ?? '') ?: $this->setting($integrationHandle, $tokenKey, '')));

        $authorizeUrl = $environmentAuthorize;
        $tokenUrl = $environmentToken;

        if ($authorizeUrl === '') {
            $authorizeUrl = trim((string) $this->setting($integrationHandle, 'authorize_url', ''));
        }

        if ($tokenUrl === '') {
            $tokenUrl = trim((string) $this->setting($integrationHandle, 'token_url', ''));
        }
        $baseUri = trim((string) (
            ($environmentSettings['base_uri'] ?? '')
            ?: $this->setting($integrationHandle, $baseUriKey, '')
            ?: $this->setting($integrationHandle, 'base_uri', '')
        ));
        $instanceUrl = trim((string) (
            ($environmentSettings['instance_url'] ?? '')
            ?: $this->setting($integrationHandle, $instanceUrlKey, '')
            ?: $this->setting($integrationHandle, 'instance_url', '')
        ));

        if ($integrationHandle === 'salesforce') {
            $host = in_array($environment, ['sandbox', 'test'], true)
                ? 'https://test.salesforce.com'
                : 'https://login.salesforce.com';

            if ($environmentAuthorize === '') {
                $authorizeUrl = $host . '/services/oauth2/authorize';
            }

            if ($environmentToken === '') {
                $tokenUrl = $host . '/services/oauth2/token';
            }
        }

        if ($baseUri === '' && $instanceUrl !== '') {
            $baseUri = rtrim($instanceUrl, '/') . '/services/data';
        }

        return [
            'authorize_url' => $authorizeUrl,
            'token_url' => $tokenUrl,
            'base_uri' => $baseUri,
            'instance_url' => $instanceUrl,
            'label' => (string) ($environmentSettings['label'] ?? $this->environmentLabel($environment)),
        ];
    }

    private function syncEnvironmentRecord(string $provider, string $integrationHandle, string $environment, array $environmentConfig): void {
        if ($provider === '' || $integrationHandle === '' || $environment === '') {
            return;
        }

        $existingDefault = IntegrationEnvironment::query()
            ->where('provider', $provider)
            ->where('integration_handle', $integrationHandle)
            ->where('is_default', true)
            ->exists();

        IntegrationEnvironment::query()->updateOrCreate(
            [
                'provider' => $provider,
                'integration_handle' => $integrationHandle,
                'environment' => $environment,
            ],
            [
                'label' => (string) ($environmentConfig['label'] ?? $this->environmentLabel($environment)),
                'is_default' => $existingDefault ? false : true,
                'settings' => [
                    'authorize_url' => $environmentConfig['authorize_url'] ?? null,
                    'token_url' => $environmentConfig['token_url'] ?? null,
                    'base_uri' => $environmentConfig['base_uri'] ?? null,
                    'instance_url' => $environmentConfig['instance_url'] ?? null,
                ],
            ]
        );
    }

    private function resolveDefinition(string $integrationHandle): ?IntegrationDefinition {
        try {
            $definition = Integrations::get($integrationHandle);
        } catch (\Throwable $exception) {
            return null;
        }

        if ($definition instanceof IntegrationDefinition) {
            return $definition;
        }

        return null;
    }

    private function resolveProviderHandle(?IntegrationDefinition $definition): string {
        if ($definition instanceof IntegrationDefinition) {
            return $definition->provider()->getHandle();
        }

        return 'framework';
    }

    private function responseErrorMessage(mixed $payload, string $fallback): string {
        if (!is_array($payload)) {
            return $fallback;
        }

        $description = trim((string) ($payload['error_description'] ?? $payload['message'] ?? ''));

        if ($description !== '') {
            return $description;
        }

        if (is_array($payload[0] ?? null)) {
            $description = trim((string) (($payload[0]['message'] ?? $payload[0]['error_description'] ?? '')));

            if ($description !== '') {
                return $description;
            }
        }

        $error = trim((string) ($payload['error'] ?? ''));

        if ($error !== '') {
            return $error;
        }

        return $fallback;
    }

    private function markConnectionRefreshFailure(IntegrationConnection $connection, string $message): void {
        $connection->fill([
            'status' => 'error',
            'status_reason' => 'token_refresh_failed',
            'last_error' => Str::limit($message, 1000),
            'last_error_at' => now(),
        ]);

        $connection->save();
    }

    private function resolveExpiry(array $tokenPayload): ?\Illuminate\Support\Carbon {
        $expiresIn = (int) ($tokenPayload['expires_in'] ?? 0);

        if ($expiresIn <= 0) {
            return null;
        }

        // Buffer by 30 seconds to reduce edge-case race conditions around expiry.
        return now()->addSeconds(max($expiresIn - 30, 0));
    }

    private function normalizeEnvironment(string $environment): string {
        $value = strtolower(trim($environment));

        return match ($value) {
            'prod', 'live', 'production' => 'production',
            'sandbox' => 'sandbox',
            'test', 'testing' => 'test',
            default => $value !== '' ? $value : 'production',
        };
    }

    private function environmentLabel(string $environment): string {
        return match ($environment) {
            'production' => 'Production',
            'sandbox' => 'Sandbox',
            'test' => 'Test',
            'live' => 'Live',
            default => ucfirst($environment),
        };
    }

    private function generateCodeVerifier(): string {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function codeChallenge(string $verifier): string {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function normaliseScopes(mixed $scopes): array {
        if (is_string($scopes)) {
            $scopes = preg_split('/[\s,]+/', trim($scopes)) ?: [];
        }

        if (!is_array($scopes)) {
            return [];
        }

        $items = array_map(fn ($scope) => trim((string) $scope), $scopes);

        return array_values(array_unique(array_filter($items)));
    }

    private function integrationSettings(string $integrationHandle): array {
        $allSettings = $this->frameworkSettings();
        $integrations = $allSettings['integrations'] ?? [];

        if (!is_array($integrations)) {
            return [];
        }

        $nested = $integrations[$integrationHandle] ?? null;

        if (is_array($nested)) {
            return $nested;
        }

        return $integrations;
    }

    private function setting(string $integrationHandle, string $key, mixed $default = null): mixed {
        $allSettings = $this->frameworkSettings();
        $integrations = is_array($allSettings['integrations'] ?? null) ? $allSettings['integrations'] : [];
        $nested = $integrations[$integrationHandle] ?? null;

        $prefixed = $integrationHandle . '_' . $key;

        if (is_array($nested) && array_key_exists($prefixed, $nested)) {
            return $nested[$prefixed];
        }

        if (array_key_exists($prefixed, $integrations)) {
            return $integrations[$prefixed];
        }

        if (is_array($nested) && array_key_exists($key, $nested)) {
            return $nested[$key];
        }

        if (array_key_exists($key, $integrations)) {
            return $integrations[$key];
        }

        return $default;
    }

    private function frameworkSettings(): array {
        if (function_exists('get_option')) {
            $settings = get_option('meros_framework_settings', []);
            return is_array($settings) ? $settings : [];
        }

        return [];
    }

    private function callbackUrl(): string {
        if (function_exists('admin_url')) {
            $base = admin_url('admin-post.php');

            if (function_exists('add_query_arg')) {
                return add_query_arg(['action' => 'meros_integration_oauth_callback'], $base);
            }

            $separator = str_contains($base, '?') ? '&' : '?';
            return $base . $separator . 'action=meros_integration_oauth_callback';
        }

        return '/wp-admin/admin-post.php?action=meros_integration_oauth_callback';
    }

    private function integrationsSettingsUrl(string $integrationHandle): string {
        if (function_exists('admin_url') && function_exists('add_query_arg')) {
            return add_query_arg([
                'page' => 'meros-features',
                'tab' => 'integrations',
                'integration' => $integrationHandle,
            ], admin_url('options-general.php'));
        }

        return '/wp-admin/options-general.php?page=meros-features&tab=integrations&integration=' . rawurlencode($integrationHandle);
    }

    private function logWarning(string $event, array $context = []): void {
        $safeContext = array_filter([
            'event' => $event,
            'integration_handle' => $context['integration_handle'] ?? null,
            'environment' => $context['environment'] ?? null,
            'connection_id' => $context['connection_id'] ?? null,
            'status' => $context['status'] ?? null,
            'error' => $context['error'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            Log::warning('Meros integration OAuth warning', $safeContext);
        } catch (\Throwable $exception) {
            // Logging may be unavailable in lightweight runtimes (for example isolated unit tests).
        }
    }
}
