<?php

namespace MM\Meros\Services\Contracts\Integrations;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Integration;
use MM\Meros\Services\Contracts\Integrations\OAuthIntegration;

use MM\Meros\App\Models\IntegrationConnection;

use MM\Meros\Facades\Integrations;

abstract class ExternalModel {
    /**
     * The unique identifier for the integration that this model is associated with.
     * Must be set in concrete subclasses to ensure proper integration resolution.
     *
     * @var string
     */
    protected string $integrationID = '';

    /**
     * The base URI for the external service's API. This should be set in concrete subclasses.
     *
     * @var string
     */
    protected string $baseUri = '';

    /**
     * An associative array mapping HTTP methods (e.g., 'get', 'post') to their corresponding endpoint URLs.
     * This allows for flexible configuration of endpoints for different request types.
     *
     * @var array<string, string>
     */
    protected array $endpoints = [];

    /**
     * Where conditions for filtering the data. Each condition is an associative array with keys:
     * - field: The field name to filter on.
     * - operator: The comparison operator (e.g., '=', 'IN', 'LIKE').
     * - value: The value to compare against.
     * 
     * @var array<int, array{field:string,operator:string,value:mixed}>
     */
    protected array $wheres = [];

    /**
     * Select fields to retrieve from the external service. This is an array of field names.
     * 
     * @var array<int, string>
     */
    protected array $selects = [];

    /**
     * The maximum number of records to retrieve. If null, no limit is applied.
     * 
     * @var int|null
     */
    protected ?int $limitValue = null;

    /**
     * The query parameter key used for selecting specific fields in the request. Defaults to 'fields'.
     *
     * @var string
     */
    protected string $selectQueryKey = 'fields';

    /**
     * The query parameter key used for limiting the number of records in the request. Defaults to 'limit'.
     *
     * @var string
     */
    protected string $limitQueryKey = 'limit';

    /**
     * The request array that holds the details of the HTTP request to be sent.
     * This includes method, URL, headers, payload, and format.
     *
     * @var array<string, mixed>
     */
    protected array $request = [];

    /**
     * The resolved Integration instance associated with this model. This is set during initialisation.
     *
     * @var Integration|null
     */
    protected ?Integration $integration = null;

    /**
     * The resolved IntegrationConnection instance associated with this model. This is set when a connection is specified.
     *
     * @var IntegrationConnection|null
     */
    protected ?IntegrationConnection $connection = null;

    /**
     * The HTTP client used for sending requests to external services. This is an instance of the HttpClient class.
     *
     * @var HttpClient
     */
    protected HttpClient $httpClient;


    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function __construct() {
        $this->httpClient = new HttpClient();
        $this->resolveIntegration();

        if ($this->integration instanceof Integration) {
            $this->request['headers'] = $this->integration->getAuthorisationHeaders();
        }

        if ($this->integration instanceof OAuthIntegration) {
            $this->connection = $this->integration->getConnection('default');
        }

        if (empty($this->baseUri)) {
            $this->baseUri = $this->integration->getBaseUri() ?? '';
        }
    }

    public static function __callStatic(string $name, array $arguments): mixed {
        $instance = new static();

        if (!method_exists($instance, $name)) {
            throw new \BadMethodCallException("Method {$name} does not exist on " . static::class);
        }

        return $instance->$name(...$arguments);
    }

    protected function resolveIntegration(): void {
        $this->integration = Integrations::get($this->integrationID);

        if ($this->integration === null) {
            throw new \Exception("Integration with ID {$this->integrationID} not found.");
        }
    }

    // =========================================================================
    // Connection Setters
    // =========================================================================

    public function withConnection(string $connectionId): static {
        if (!$this->integration) {
            throw new \Exception("Integration not resolved for " . static::class);
        }

        if (!$this->integration instanceof OAuthIntegration) {
            throw new \Exception("Integration {$this->integration->getHandle()} does not support OAuth connections.");
        }

        $connection = $this->integration->getConnection($connectionId);

        if (!$connection) {
            throw new \Exception("Connection with ID {$connectionId} not found for integration " . $this->integration->getHandle());
        }

        $this->connection = $connection;
        $this->request['headers'] = $this->integration->getAuthorisationHeaders();

        return $this;
    }

    // =========================================================================
    // HTTP Request Setters
    // =========================================================================

    /**
     * Sets the headers for the HTTP request.
     *
     * @param array $headers An associative array of headers to set for the request.
     * @param bool  $merge   If true, merges the provided headers with existing ones; otherwise, replaces them.
     *                       Defaults to true to ensure that authorisation headers are preserved unless explicitly overridden.
     * 
     * @return static Returns the current instance for method chaining.
     */
    public function headers(array $headers, bool $merge = true): static {
        if ($merge && isset($this->request['headers'])) {
            $this->request['headers'] = array_merge($this->request['headers'], $headers);
        } else {
            $this->request['headers'] = $headers;
        }

        return $this;
    }

    public function baseUri(string $baseUri): static {
        $this->baseUri = rtrim($baseUri, '/');
        return $this;
    }

    public function endpoint(string $method, string $endpoint): static {
        $this->endpoints[strtolower($method)] = $endpoint;
        return $this;
    }

    public function endpoints(array $endpoints): static {
        foreach ($endpoints as $method => $endpoint) {
            if (!is_string($method) || !is_string($endpoint) || $endpoint === '') {
                continue;
            }

            $this->endpoint($method, $endpoint);
        }

        return $this;
    }

    // =========================================================================
    // Query Setters
    // =========================================================================

    public function where(string $field, mixed $operatorOrValue, mixed $value = null): static {
        $operator = '=';
        $resolvedValue = $operatorOrValue;

        if ($value !== null) {
            $operator = strtoupper((string) $operatorOrValue);
            $resolvedValue = $value;
        }

        $this->wheres[] = [
            'field'    => $field,
            'operator' => $operator,
            'value'    => $resolvedValue,
        ];

        return $this;
    }

    public function whereIn(string $field, array $values): static {
        return $this->where($field, 'IN', $values);
    }

    public function select(array|string $fields): static {
        if (is_string($fields)) {
            $fields = array_filter(array_map('trim', explode(',', $fields)));
        }

        $fields = array_values(array_filter($fields, static fn ($field) => is_string($field) && trim($field) !== ''));

        if ($fields !== []) {
            $this->selects = $fields;
        }

        return $this;
    }

    public function limit(int $limit): static {
        $this->limitValue = max(1, $limit);
        return $this;
    }

    public function query(array $query): static {
        $operators = [
            '=', '!=', '<>', '>', '>=', '<', '<=',
            'LIKE', 'NOT LIKE', 'IN', 'NOT IN',
            'IS', 'IS NOT',
        ];

        foreach ($query as $field => $value) {
            if (!is_string($field) || $field === '') {
                continue;
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    if (
                        count($value) === 2
                        && is_string($value[0])
                        && in_array(strtoupper(trim($value[0])), $operators, true)
                    ) {
                        $this->where($field, strtoupper(trim($value[0])), $value[1]);
                        continue;
                    }

                    $this->whereIn($field, $value);
                    continue;
                }

                $operator = $value['operator'] ?? $value['op'] ?? '=';
                $resolvedValue = $value['value'] ?? null;

                $this->where($field, (string) $operator, $resolvedValue);
                continue;
            }

            $this->where($field, $value);
        }

        return $this;
    }

    public function first(array $query = []): ?array {
        $this->limit(1);

        $records = $this->get($query);

        $record = $records->first();

        return is_array($record) ? $record : null;
    }

    // =========================================================================
    // Query Building and Execution
    // =========================================================================

    protected function resolveConfiguredEndpoint(string $method): string {
        $normalised = strtolower($method);

        return $this->endpoints[$normalised] ?? '';
    }

    protected function resolveEndpointFor(string $method, array $context = []): string {
        $endpoint = $this->resolveConfiguredEndpoint($method);

        if ($endpoint === '' && strtolower($method) === 'create') {
            $endpoint = $this->resolveConfiguredEndpoint('post');
        }

        return $endpoint;
    }

    protected function applyQueryConstraints(array $query): array {
        $query = $this->applyWhereConstraints($query);
        $query = $this->applySelectConstraints($query);

        return $this->applyLimitConstraint($query);
    }

    protected function applyWhereConstraints(array $query): array {
        foreach ($this->wheres as $where) {
            $query = $this->applyWhereConstraint($query, $where);
        }

        return $query;
    }

    protected function applyWhereConstraint(array $query, array $where): array {
        $field = $where['field'] ?? '';
        $operator = strtoupper((string) ($where['operator'] ?? '='));
        $value = $where['value'] ?? null;

        if (!is_string($field) || $field === '') {
            return $query;
        }

        if ($operator === '=') {
            $query[$field] = $value;
            return $query;
        }

        $query['filters'][] = [
            'field'    => $field,
            'operator' => $operator,
            'value'    => $value,
        ];

        return $query;
    }

    protected function applySelectConstraints(array $query): array {
        if ($this->selects === []) {
            return $query;
        }

        $query[$this->selectQueryKey] = implode(',', $this->selects);

        return $query;
    }

    protected function applyLimitConstraint(array $query): array {
        if ($this->limitValue === null) {
            return $query;
        }

        $query[$this->limitQueryKey] = $this->limitValue;

        return $query;
    }

    abstract protected function formatQuery(array $query): array;

    protected function formatPayload(array $payload, string $method): array {
        return $payload;
    }

    protected function buildRequest(string $method, string $endpoint, array $query = []): void {
        $this->request['method'] = strtoupper($method);
        $this->request['url'] = $this->baseUri . '/' . ltrim($endpoint, '/');
        $this->request['payload'] = $query;
        $this->request['format'] = 'json';
    }

    // =========================================================================
    // Query Execution and Response Handling
    // =========================================================================

    public function get(array $query = []): Collection {
        if ($query !== []) {
            $this->query($query);
        }

        try {
            $response = $this->getRaw();
            $payload = $this->normaliseResponsePayload($response);

            return collect($this->extractRecordsFromPayload($payload));
        } finally {
            $this->clearConstraints();
        }
    }

    public function create(array $attributes = []): array {
        $response = $this->createRaw($attributes);

        return $this->normaliseResponsePayload($response);
    }

    
    public function createRaw(array $attributes = []): Response {
        $endpoint = $this->resolveEndpointFor('post', [
            'payload' => $attributes,
            'operation' => 'create',
        ]);

        if (empty($endpoint)) {
            $endpoint = $this->resolveEndpointFor('create', [
                'payload' => $attributes,
                'operation' => 'create',
            ]);
        }

        if (empty($endpoint)) {
            throw new \Exception('No endpoint defined for POST request.');
        }

        $this->buildRequest('POST', $endpoint, $this->formatPayload($attributes, 'POST'));

        try {
            return $this->send();
        } finally {
            $this->clearConstraints();
        }
    }

    public function getRaw(array $query = []): Response {
        if ($query !== []) {
            $this->query($query);
        }

        $builtQuery = $this->applyQueryConstraints([]);

        $endpoint = $this->resolveEndpointFor('get', [
            'query' => $builtQuery,
            'operation' => 'get',
        ]);

        if (empty($endpoint)) {
            throw new \Exception('No endpoint defined for GET request.');
        }

        $this->buildRequest('GET', $endpoint, $this->formatQuery($builtQuery));

        try {
            return $this->send();
        } finally {
            $this->clearConstraints();
        }
    }

    protected function send(): Response {
        if (empty($this->request)) {
            throw new \Exception('No request has been built. Please call a method like get() before sending the request.');
        }

        return $this->httpClient->send($this->request);
    }

    protected function normaliseResponsePayload(Response $response): array {
        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    protected function extractRecordsFromPayload(array $payload): array {
        foreach (['data', 'items', 'records'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return $payload[$key];
            }
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        if ($payload === []) {
            return [];
        }

        return [$payload];
    }
    // =========================================================================
    // Resetting
    // =========================================================================

    public function clearConstraints(): static {
        $this->wheres = [];
        $this->selects = [];
        $this->limitValue = null;
        return $this;
    }

    public function clearRequest(): static {
        $this->request = [];
        return $this;
    }
}