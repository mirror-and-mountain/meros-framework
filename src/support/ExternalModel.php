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

/**
 * Abstract base class for external models that interact with third-party integrations.
 *
 * This class provides a fluent interface for configuring and sending HTTP requests to external services
 * based on integration definitions, accounts, and connections. It handles authentication, request building,
 * and response handling, including automatic token refresh for OAuth-based integrations.
 */
abstract class ExternalModel {
    /**
     * The integration handle to identify the specific integration.
     *
     * @var string
     */
    protected string $integrationHandle = '';

    /**
     * The label of the connection to use for the request.
     *
     * @var string
     */
    protected string $connectionLabel = '';

    /**
     * The environment to use for the integration (if applicable).
     *
     * @var string
     */
    protected string $environment = '';

    /**
     * The path for the request, relative to the integration's base URI.
     *
     * @var string
     */
    protected string $path = '';

    /**
     * The HTTP method for the request (e.g., GET, POST).
     *
     * @var string
     */
    protected string $method = 'GET';

    /**
     * The format for the request payload (e.g., json, form).
     *
     * @var string
     */
    protected string $format = 'json';

    /**
     * The query parameters for the request.
     *
     * @var array
     */
    protected array $query = [];

    /**
     * The headers for the request.
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * The payload for the request.
     *
     * @var array
     */
    protected array $payload = [];

    /**
     * The resolved integration account instance.
     *
     * @var IntegrationAccount|null
     */
    protected ?IntegrationAccount $integration = null;

    /**
     * The resolved integration definition instance.
     *
     * @var IntegrationDefinition|null
     */
    protected ?IntegrationDefinition $definition = null;

    /**
     * The resolved integration connection instance.
     *
     * @var IntegrationConnection|null
     */
    protected ?IntegrationConnection $connection = null;

    /**
     * The HTTP client used for sending requests.
     *
     * @var HttpClient
     */
    protected HttpClient $httpClient;

    /**
     * The authentication resolver used for handling integration authentication.
     *
     * @var AuthResolver
     */
    protected AuthResolver $authResolver;

    public function __construct()
    {
        $this->httpClient = new HttpClient();
        $this->authResolver = new AuthResolver();
    }

    /**
     * Creates a new model instance with an optional connection label.
     */
    public static function init(string $connectionLabel = ''): static {
        $instance = new static();

        if ($connectionLabel !== '') {
            $instance->using($connectionLabel);
        }

        return $instance;
    }
    
    /**
     * Allows Laravel-like static calls on external models.
     */
    public static function __callStatic(string $method, array $arguments): mixed {
        $instance = new static();

        if (!method_exists($instance, $method)) {
            throw new \BadMethodCallException('Method ' . static::class . '::' . $method . ' does not exist.');
        }

        return $instance->{$method}(...$arguments);
    }

    /**
     * Sets the integration handle for the request.
     *
     * @param string $handle The integration handle.
     *
     * @return static
     */
    public function integration(string $handle): static {
        $this->integrationHandle = $handle;
        $this->integration = null;
        $this->connection = null;

        return $this;
    }

    /**
     * Sets the connection label for the request.
     *
     * @param string $connectionLabel The connection label.
     *
     * @return static
     */
    public function using(string $connectionLabel): static {
        $normalised = trim($connectionLabel);

        if (in_array(strtolower($normalised), ['default', 'active', 'current'], true)) {
            $normalised = '';
        }

        $this->connectionLabel = $normalised;
        $this->connection = null;

        return $this;
    }

    /**
     * Sets the environment for the request.
     *
     * @param string $environment The environment name.
     *
     * @return static
     */
    public function usingEnvironment(string $environment): static {
        $this->environment = $environment;
        $this->integration = null;
        $this->connection = null;

        return $this;
    }

    /**
     * Sets the path for the request.
     *
     * @param string $path The request path e.g., '/users'.
     *
     * @return static
     */
    public function path(string $path): static {
        $this->path = ltrim($path, '/');

        return $this;
    }

    /**
     * Sets the query parameters for the request.
     *
     * @param array $query
     *
     * @return static
     */
    public function query(array $query): static {
        $this->query = array_merge($this->query, $query);

        return $this;
    }

    /**
     * Sets the request headers for the request.
     *
     * @param array $headers
     *
     * @return static
     */
    public function headers(array $headers): static {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Sets the request payload for the request.
     *
     * @param array $payload
     *
     * @return static
     */
    public function payload(array $payload): static {
        $this->payload = array_merge($this->payload, $payload);

        return $this;
    }

    /**
     * Shortcut to set the request format to JSON.
     *
     * @return static
     */
    public function asJson(): static {
        $this->format = 'json';

        return $this;
    }

    /**
     * Shortcut to set the request format to form data.
     *
     * @return static
     */
    public function asForm(): static {
        $this->format = 'form';

        return $this;
    }

    /**
     * Sets the HTTP method to GET for the request.
     *
     * Subclasses may override this to provide model-style semantics.
     *
     * @param string $path
     *
     * @return mixed
     */
    public function get(string $path = ''): mixed {
        return $this->request('GET', $path);
    }

    /**
     * Sets the HTTP method to POST for the request.
     *
     * @param string $path
     *
     * @return Response
     */
    public function post(string $path = ''): Response {
        return $this->request('POST', $path);
    }

    /**
     * Sets the HTTP method to PUT for the request.
     *
     * @param string $path
     *
     * @return Response
     */
    public function put(string $path = ''): Response {
        return $this->request('PUT', $path);
    }

    /**
     * Sets the HTTP method to PATCH for the request.
     *
     * @param string $path
     *
     * @return Response
     */
    public function patch(string $path = ''): Response {
        return $this->request('PATCH', $path);
    }

    /**
     * Sets the HTTP method to DELETE for the request.
     *
     * Subclasses may override this to provide model-style semantics.
     *
     * @param string $path
     *
     * @return mixed
     */
    public function delete(string $path = ''): mixed {
        return $this->request('DELETE', $path);
    }

    /**
     * Sends the HTTP request to the external service based on the configured parameters.
     *
     * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE).
     * @param string $path The request path relative to the integration's base URI.
     *
     * @return Response The HTTP response from the external service.
     */
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

    /**
     * Resolves the integration definition based on the provided integration handle.
     *
     * @return IntegrationDefinition The resolved integration definition.
     *
     * @throws \LogicException If the integration handle is not set.
     * @throws \RuntimeException If no registered integration definition is found for the handle.
     */
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

    /**
     * Resolves the integration account based on the provided integration handle and environment.
     *
     * @return IntegrationAccount The resolved integration account.
     *
     * @throws \LogicException If the integration handle is not set.
     * @throws \RuntimeException If no active integration account is found for the handle and environment.
     */
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

    /**
     * Resolves the integration connection based on the provided integration account and connection label.
     *
     * @param IntegrationAccount $integration The integration account instance.
     *
     * @return IntegrationConnection The resolved integration connection.
     *
     * @throws \RuntimeException If no active connection is found for the integration account and label.
     */
    protected function resolveConnection(IntegrationAccount $integration): IntegrationConnection {
        if ($this->connection instanceof IntegrationConnection) {
            return $this->connection;
        }

        $baseQuery = $integration->connections()->where('is_active', true);

        $connection = null;

        if ($this->connectionLabel !== '') {
            $connection = (clone $baseQuery)
                ->where('label', $this->connectionLabel)
                ->first();
        }

        // Global fallback: if a specific label is unavailable, use the most recent active connection.
        if (!$connection instanceof IntegrationConnection) {
            $connection = (clone $baseQuery)
                ->orderByDesc('last_used_at')
                ->orderByDesc('connected_at')
                ->orderByDesc('id')
                ->first();
        }

        if (!$connection instanceof IntegrationConnection) {
            throw new \RuntimeException('No active connection was found for integration handle: ' . $integration->integration_handle);
        }

        $this->connection = $connection;

        return $connection;
    }

    /**
     * Resolves the endpoint URL based on the provided path.
     *
     * @param string $path The endpoint path relative to the base path.
     *
     * @return string The resolved endpoint URL.
     */
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

    /**
     * Builds the HTTP request array based on the integration definition, connection, endpoint, and method.
     *
     * @param IntegrationDefinition $definition The integration definition instance.
     * @param IntegrationConnection $connection The integration connection instance.
     * @param string $endpoint The endpoint URL relative to the base URI.
     * @param string $method The HTTP method (GET, POST, PUT, PATCH, DELETE).
     *
     * @return array The built request array containing method, URL, headers, payload, and format.
     */
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
        $integrationHandle = trim((string) ($connection->account?->integration_handle ?? $this->integrationHandle));

        $baseUri = trim($metadataBaseUri);

        if ($baseUri === '' && trim($metadataInstanceUrl) !== '') {
            $baseUri = rtrim($metadataInstanceUrl, '/') . '/services/data';
        }

        // Salesforce OAuth APIs should use the instance URL returned by token exchange,
        // not a static login/My Domain base URI from settings.
        if ($integrationHandle === 'salesforce' && trim($metadataInstanceUrl) !== '') {
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

    /**
     * Sends the HTTP request to the external service based on the configured parameters.
     *
     * @param array $request The request array containing method, URL, headers, payload, and format.
     *
     * @return Response The HTTP response from the external service.
     */
    protected function send(array $request): Response {
        return $this->httpClient->send($request);
    }

    /**
     * Throws a RuntimeException if the HTTP response indicates a failure.
     *
     * @param Response $response
     * @param string $action
     *
     * @return void
     */
    protected function throwIfFailed(Response $response, string $action): void {
        if ($response->failed()) {
            throw new \RuntimeException('Failed to ' . $action . ': ' . $response->body());
        }
    }

    /**
     * Executes a callback against a temporary path and restores the prior path afterward.
     *
     * @param string $path
     * @param callable $callback
     *
     * @return mixed
     */
    protected function withPath(string $path, callable $callback): mixed {
        $originalPath = $this->path;
        $this->path($path);

        try {
            return $callback();
        } finally {
            $this->path($originalPath);
        }
    }

    /**
     * Resets the request parameters to their default values.
     *
     * This method clears the path, method, format, query parameters, headers, payload, and environment,
     * allowing for a fresh configuration of the request.
     */
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