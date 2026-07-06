<?php

namespace MM\Meros\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Schema;

use MM\Meros\App\Models\IntegrationAccount;
use MM\Meros\App\Models\IntegrationConnection;
use MM\Meros\Facades\Integrations as IntegrationsAccessor;
use MM\Meros\Services\Contracts\Integration as IntegrationDefinition;
use MM\Meros\Support\Integrations\HttpClient;
use MM\Meros\Support\Integrations\IntegrationConnectionSecrets;
use MM\Meros\Support\Integrations\AuthResolver;
use MM\Meros\Support\Integrations\OAuthManager;

abstract class ExternalModel {
    protected string $integrationHandle = '';

    protected string $connectionLabel = '';

    protected string $environment = '';

    protected string $path = '';

    protected string $method = 'GET';

    protected string $format = 'json';

    protected array $query = [];

    protected array $headers = [];

    protected array $payload = [];

    protected ?IntegrationAccount $integration = null;

    protected ?IntegrationDefinition $definition = null;

    protected ?IntegrationConnection $connection = null;

    protected HttpClient $httpClient;

    protected AuthResolver $authResolver;

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->authResolver = new AuthResolver();
    }

    public function integration(string $handle): static {
        $this->integrationHandle = $handle;
        $this->integration = null;
        $this->connection = null;

        return $this;
    }

    public function using(string $connectionLabel): static {
        $this->connectionLabel = $connectionLabel;
        $this->connection = null;

        return $this;
    }

    public function usingEnvironment(string $environment): static {
        $this->environment = $environment;
        $this->integration = null;
        $this->connection = null;

        return $this;
    }

    public function path(string $path): static {
        $this->path = ltrim($path, '/');

        return $this;
    }

    public function query(array $query): static {
        $this->query = array_merge($this->query, $query);

        return $this;
    }

    public function headers(array $headers): static {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function payload(array $payload): static {
        $this->payload = array_merge($this->payload, $payload);

        return $this;
    }

    public function asJson(): static {
        $this->format = 'json';

        return $this;
    }

    public function asForm(): static {
        $this->format = 'form';

        return $this;
    }

    public function get(string $path = ''): Response {
        return $this->request('GET', $path);
    }

    public function post(string $path = ''): Response {
        return $this->request('POST', $path);
    }

    public function put(string $path = ''): Response {
        return $this->request('PUT', $path);
    }

    public function patch(string $path = ''): Response {
        return $this->request('PATCH', $path);
    }

    public function delete(string $path = ''): Response {
        return $this->request('DELETE', $path);
    }

    public function request(string $method, string $path = ''): Response {
        $definition  = $this->resolveDefinition();
        $integration = $this->resolveIntegration();
        $connection  = $this->resolveConnection($integration);

        // Refresh proactively when possible if the token is already expired.
        if ($definition->getAuthType() === 'oauth' && $connection->secrets()->isExpired() && $connection->secrets()->hasRefreshToken()) {
            app(OAuthManager::class)->refreshConnectionToken($connection);
            $connection->refresh();
        }

        $endpoint    = $this->resolveEndpoint($path);
        $request     = $this->buildRequest($definition, $connection, $endpoint, $method);

        $response = $this->send($request);

        // Retry one time after refreshing when OAuth calls return unauthorized.
        if (
            $response->status() === 401
            && $definition->getAuthType() === 'oauth'
            && $connection->secrets()->hasRefreshToken()
            && app(OAuthManager::class)->refreshConnectionToken($connection)
        ) {
            $connection->refresh();
            $request = $this->buildRequest($definition, $connection, $endpoint, $method);
            $response = $this->send($request);
        }

        if ($response->ok()) {
            $connection->last_used_at = now();

            if (property_exists($connection, 'status') || array_key_exists('status', $connection->getAttributes())) {
                $connection->status = 'active';
                $connection->status_reason = 'request_ok';
                $connection->last_error = null;
                $connection->last_error_at = null;
            }

            $connection->save();
        }

        return $response;
    }

    protected function resolveDefinition(): IntegrationDefinition {
        if ($this->definition instanceof IntegrationDefinition) {
            return $this->definition;
        }

        if ($this->integrationHandle === '') {
            throw new \LogicException(static::class . ' requires an integration handle. Call integration("...") before making a request.');
        }

        $definition = IntegrationsAccessor::get($this->integrationHandle);

        if (!$definition instanceof IntegrationDefinition) {
            throw new \RuntimeException('No registered integration definition was found for handle: ' . $this->integrationHandle);
        }

        $this->definition = $definition;

        return $definition;
    }

    protected function resolveIntegration(): IntegrationAccount {
        if ($this->integration instanceof IntegrationAccount) {
            return $this->integration;
        }

        if ($this->integrationHandle === '') {
            throw new \LogicException(static::class . ' requires an integration handle. Call integration("...") before making a request.');
        }

        $query = IntegrationAccount::query()
            ->where('integration_handle', $this->integrationHandle)
            ->where('is_active', true);

        $hasEnvironmentColumn = Schema::hasColumn('meros_integration_accounts', 'environment');

        if ($hasEnvironmentColumn && $this->environment !== '') {
            $query->where('environment', $this->environment);
        }

        $integration = $query->first();

        if (!$integration instanceof IntegrationAccount) {
            throw new \RuntimeException('No active integration account was found for handle: ' . $this->integrationHandle);
        }

        $this->integration = $integration;

        return $integration;
    }

    protected function resolveConnection(IntegrationAccount $integration): IntegrationConnection {
        if ($this->connection instanceof IntegrationConnection) {
            return $this->connection;
        }

        $query = $integration->connections()->where('is_active', true);

        if ($this->connectionLabel !== '') {
            $query->where('label', $this->connectionLabel);
        }

        $connection = $query->first();

        if (!$connection instanceof IntegrationConnection) {
            throw new \RuntimeException('No active connection was found for integration handle: ' . $integration->integration_handle);
        }

        $this->connection = $connection;

        return $connection;
    }

    protected function resolveEndpoint(string $path): string {
        $basePath = trim($this->path, '/');
        $path     = trim($path, '/');

        if ($path === '' && $basePath === '') {
            return '';
        }

        if ($path === '') {
            return $basePath;
        }

        if ($basePath === '') {
            return $path;
        }

        return $basePath . '/' . $path;
    }

    protected function buildRequest(
        IntegrationDefinition $definition,
        IntegrationConnection $connection,
        string $endpoint,
        string $method
    ): array {
        $secrets = new IntegrationConnectionSecrets($connection);

        $accountSettings = $connection->account?->settings;
        $accountOauthSettings = is_array($accountSettings['oauth'] ?? null) ? $accountSettings['oauth'] : [];
        $metadataBaseUri = (string) ($secrets->metadata('base_uri') ?? '');
        $metadataInstanceUrl = (string) ($secrets->metadata('instance_url') ?? '');

        $baseUri = trim($metadataBaseUri);

        if ($baseUri === '' && trim($metadataInstanceUrl) !== '') {
            $baseUri = rtrim($metadataInstanceUrl, '/') . '/services/data';
        }

        if ($baseUri === '' && trim((string) ($accountOauthSettings['base_uri'] ?? '')) !== '') {
            $baseUri = trim((string) $accountOauthSettings['base_uri']);
        }

        if ($baseUri === '') {
            $baseUri = $definition->getBaseUri();
        }

        $url = rtrim($baseUri, '/');

        if ($definition->getApiVersion() !== '') {
            $url .= '/' . trim($definition->getApiVersion(), '/');
        }

        if ($endpoint !== '') {
            $url .= '/' . ltrim($endpoint, '/');
        }

        $request = [
            'method'  => strtoupper($method),
            'url'     => $url,
            'headers' => $this->authResolver->resolve($definition, $connection),
            'payload' => $this->payload,
            'format'  => $this->format,
        ];

        if ($this->query !== []) {
            $request['url'] .= '?' . http_build_query($this->query);
        }

        $requestHeaders = array_merge($request['headers'] ?? [], $this->headers);

        if ($bearerToken = $secrets->bearerToken()) {
            $requestHeaders['Authorization'] = 'Bearer ' . $bearerToken;
        }

        $request['headers'] = $requestHeaders;

        return $request;
    }

    protected function send(array $request): Response {
        return $this->httpClient->send($request);
    }

    protected function reset(): void {
        $this->path = '';
        $this->method = 'GET';
        $this->format = 'json';
        $this->query = [];
        $this->headers = [];
        $this->payload = [];
        $this->environment = '';
    }
}