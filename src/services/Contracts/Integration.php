<?php

namespace MM\Meros\Services\Contracts;

use Illuminate\Support\Str;

use MM\Meros\App\Models\ExternalConnection;

use MM\Meros\Services\Contracts\Admin\Setting;
use MM\Meros\Services\Contracts\Integrations\ExternalModel;

use MM\Meros\Support\Integrations\HttpClient;

abstract class Integration extends FeatureDefinition {
    /**
     * The unique handle for the integration.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * The human-readable label for the integration.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * The human-readable description for the integration.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The category of the integration (e.g., 'crm', 'email', 'payments').
     *
     * @var string
     */
    protected string $category = 'general';

    /**
     * The authentication type for the integration (e.g., 'api_key', 'oauth').
     *
     * @var string
     */
    protected string $authType = 'api_key';

    /**
     * An array of environments for the integration in $handle => $label format. 
     * This can be used to define different environments (e.g., 'sandbox', 'production') for the integration.
     *
     * @var array
     */
    protected array $environments = [];

    /**
     * Indicates whether the integration is enabled.
     *
     * @var bool
     */
    protected bool $enabled;

    /**
     * The integration's settings container.
     *
     * @var Setting
     */
    protected Setting $settings;

    /**
     * The configuration for the integration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * The configuration fields for the integration.
     *
     * @var array
     */
    protected array $fields = [];

    /**
     * Whether to merge the integration's fields with the default fields provided by the framework.
     * If set to true, the integration's fields will be merged with the default fields; if false, only the integration's fields will be used.
     *
     * @var boolean
     */
    protected bool $mergeFields = true;

    /**
     * An array of connections associated with this integration
     *
     * @var array<ExternalConnection>
     */
    protected array $connections = [];

    /**
     * Indicates whether the integration allows multiple connections to be established. If set to true, multiple connections can be created; if false, only a single connection is allowed.
     *
     * @var boolean
     */
    protected bool $allowMultipleConnections = false;

    /**
     * An array of external models associated with this integration. Each model should implement the ExternalModel contract.
     *
     * @var array<ExternalModel>
     */
    protected array $models = [];

    /**
     * The HTTP client for sending requests to external services.
     * 
     * @var HttpClient
     */
    final protected HttpClient $httpClient;

    /**
     * The URL for the integration's settings page in the admin dashboard.
     *
     * @var string
     */
    protected string $integrationPageUrl = '';


    // =========================================================================
    // Contract Methods
    // =========================================================================

    public function __construct(FeatureProvider $provider, array $props = []) {
        parent::__construct($provider, $props);

        $this->initRequiredProperties();

        $this->httpClient = new HttpClient();
        $this->integrationPageUrl = admin_url('options-general.php?page=meros-integrations&integration=' . $this->getHandle());
        $this->queued = true;

        $this->configure();
        $this->initConfigurationFields();
    }

    protected function initRequiredProperties(): void {
        if (empty($this->handle)) {
            throw new \InvalidArgumentException('Integration handle must be defined.');
        }

        if (empty($this->label)) {
            $this->label = Str::title(str_replace('-', ' ', $this->handle));
        }
    }

    final protected function queue(): void {
        $this->queued = true;
    }

    abstract public function getAuthorisationHeaders(): array;

    // =========================================================================
    // Initialisation
    // =========================================================================

    abstract protected function initConfigurationFields(): void;

    /**
     * This method is called during the construction of the integration instance and can be used to set up any necessary properties or perform any required setup tasks.
     *
     * @return void
     */
    protected function configure(): void {}

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the configuration fields for the integration.
     *
     * @return array
     */
    public function getFields(): array {
        if ($this->hasMultipleEnvironments()) {
            $currentEnvironment = $this->getCurrentEnvironment();
            
            if ($currentEnvironment) {
                $fields = $this->fields;
                foreach ($fields as &$field) {
                    $field['name'] = $field['name'] . '_' . $currentEnvironment;
                }

                return $fields;
            }
        }

        return $this->fields;
    }

    /**
     * Returns the required configuration fields for the integration.
     *
     * @return array
     */
    public function getRequiredFields(): array {
        return array_filter($this->getFields(), function ($field) {
            return isset($field['required']) && $field['required'] === true;
        });
    }

    /**
     * Returns the unique handle for the integration.
     *
     * @return string
     */
    public function getHandle(bool $snakeCase = false): string {
        return $snakeCase ? Str::replace('-', '_', Str::snake($this->handle)) : $this->handle;
    }

    /**
     * Returns the human-readable label for the integration.
     *
     * @return string
     */
    public function getLabel(): string {
        return $this->label;
    }

    /**
     * Returns the human-readable description for the integration.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns the category of the integration.
     *
     * @return string
     */
    public function getCategory(): string {
        return $this->category;
    }

    /**
     * Returns the authentication type for the integration.
     *
     * @return string
     */
    public function getAuthType(): string {
        return $this->authType;
    }

    /**
     * Returns the URL for the integration's settings page in the admin dashboard.
     *
     * @return string
     */
    public function getIntegrationPageUrl(): string {
        return $this->integrationPageUrl;
    }

    // =========================================================================
    // Settings Management
    // =========================================================================

    /**
     * Returns whether the integration is enabled.
     * 
     * @param bool $refresh Whether to refresh the enabled status from the database. Default is false.
     *
     * @return bool
     */
    public function isEnabled(bool $refresh = false): bool {
        if (isset($this->enabled)) {
            return $this->enabled;
        } 
        
        else {
            $this->enabled = $this->getSettingValue('enabled', $refresh) ?? false;
        }

        return $this->enabled;
    }

    /**
     * Returns the integration's settings container.
     *
     * @return Setting The integration's settings container.
     */
    public function getSettingsContainer(): Setting {
        return $this->settings;
    }

    /**
     * Returns the values of the integration's settings, optionally filtered by a specific key and environment.
     * Settings are merged with the integration's configuration if applicable.
     *
     * @param string|bool $key
     * @param string|bool $forEnvironment
     * @param bool        $refresh
     *
     * @return mixed
     */
    public function settings(string|bool $key = '', string|bool $forEnvironment = 'current', bool $refresh = false): mixed {
        if (is_bool($key)) {
            $refresh = $key;
            $key = '';
        }
    
        else if (is_bool($forEnvironment)) {
            $refresh = $forEnvironment;
            $forEnvironment = $key;
            $key = '';
        }

        if (!empty($key) && in_array($key, array_keys($this->config))) {
            return $this->config[$key];
        }

        $camelKey = Str::camel($key);
        if (!empty($key) && property_exists($this, $camelKey) && isset($this->{$camelKey}) && !empty($this->{$camelKey})) {
            return $this->{$camelKey};
        }

        if (!empty($key) && property_exists($this, $key) && isset($this->{$key}) && !empty($this->{$key})) {
            return $this->{$key};
        }

        if ($forEnvironment === 'current' && $this->hasMultipleEnvironments()) {
            $forEnvironment = $this->getCurrentEnvironment($refresh) ?? '';
        }

        if (!empty($forEnvironment) && $this->hasMultipleEnvironments()) {
            $environmentSettings = $this->getEnvironmentSettings($forEnvironment, $refresh);
            
            if (empty($key)) {
                return array_merge($this->config, $environmentSettings);
            }

            return $environmentSettings[$key . '_' . $forEnvironment] ?? null;
        }

        return array_merge($this->config, $this->settings->getValue($refresh));
    }

    /**
     * Returns the value of a specific setting for the integration.
     *
     * @param string $settingName The name of the setting to retrieve.
     * @param bool   $refresh      Whether to refresh the setting value from the database. Default is false.
     *
     * @return mixed The value of the specified setting, or null if the setting does not exist.
     */
    public function getSettingValue(string $settingName, bool $refresh = false): mixed {
        if ($settingName === 'current_environment') {
            $settingName = $this->handle . '_current_environment';
        }

        if ($settingName === 'enabled') {
            $settingName = $this->handle . '_enabled';
        }

        $setting = $this->settings->get($settingName);
        
        if ($setting === null) {
            return null;
        }

        return $setting->getValue($refresh);
    }

    /**
     * Returns the value of a specific configuration key for the integration.
     *
     * @param string $configKey The key of the configuration to retrieve.
     * @param mixed  $default   The default value to return if the configuration key does not exist. Default is null.
     *
     * @return mixed The value of the specified configuration key, or the default value if the key does not exist.
     */
    public function getConfigValue(string $configKey, mixed $default = null): mixed {
        return $this->config[$configKey] ?? $default;
    }

    /**
     * Returns the value of a specific property for the integration.
     *
     * @param string $propertyKey The key of the property to retrieve.
     * @param mixed  $default   The default value to return if the property does not exist. Default is null.
     *
     * @return mixed The value of the specified property, or the default value if the property does not exist.
     */
    public function getPropertyValue(string $propertyKey, mixed $default = null): mixed {
        $propertyKey = Str::camel($propertyKey);

        if (property_exists($this, $propertyKey)) {
            return isset($this->{$propertyKey}) && !empty($this->{$propertyKey}) ? $this->{$propertyKey} : $default;
        }

        return $default;
    }

    // =========================================================================
    // Environment Management
    // =========================================================================

    /**
     * Returns whether the integration has multiple environments defined.
     *
     * @return bool
     * 
     */
    public function hasMultipleEnvironments(): bool {
        return count($this->environments) > 1;
    }

    /**
     * Returns the environments set for the integration.
     *
     * @return array
     */
    public function getEnvironments(): array {
        return $this->environments;
    }

    /**
     * Returns the current environment for the integration.
     * 
     * @param bool $refresh Whether to refresh the current environment value from the database. Default is false.
     *
     * @return string|null The current environment for the integration, or null if not set.
     */
    public function getCurrentEnvironment(bool $refresh = false): ?string {
        if ($this->hasMultipleEnvironments()) {
            return $this->getSettingValue('current_environment', $refresh);
        }

        return 'default';
    }

    /**
     * Returns the values of all settings for a specific environment of the integration.
     *
     * @param string $environmentHandle The handle of the environment for which to retrieve settings.
     * @param bool   $refresh            Whether to refresh the settings values from the database. Default is false.
     *
     * @return array The values of all settings for the specified environment.
     *
     * @throws \InvalidArgumentException If the specified environment handle is not defined for this integration.
     * @throws \RuntimeException         If the settings for the specified environment handle are not defined.
     */
    public function getEnvironmentSettings(string $environmentHandle, bool $refresh = false): array {
        if (!isset($this->environments[$environmentHandle])) {
            throw new \InvalidArgumentException("Environment handle '{$environmentHandle}' is not defined for this integration.");
        }

        $environmentSettings = $this->settings->get($this->handle . '_' . $environmentHandle . '_settings');
        if ($environmentSettings === null) {
            throw new \RuntimeException("Settings for environment handle '{$environmentHandle}' are not defined.");
        }

        return $environmentSettings->getValue($refresh);
    }

    /**
     * Sets the current environment for the integration.
     *
     * @param string $environmentHandle The handle of the environment to set as current.
     *
     * @throws \InvalidArgumentException If the specified environment handle is not defined for this integration.
     * @throws \RuntimeException         If the setting for the current environment is not defined.
     */
    public function setCurrentEnvironment(string $environmentHandle): void {
        if (!isset($this->environments[$environmentHandle])) {
            throw new \InvalidArgumentException("Environment handle '{$environmentHandle}' is not defined for this integration.");
        }

        $setting = $this->settings->get($this->handle . '_current_environment');
        if ($setting === null) {
            throw new \RuntimeException("Setting for current environment is not defined.");
        }

        $setting->updateValue($environmentHandle);
    }

    // =========================================================================
    // Connection Management
    // =========================================================================

    /**
     * Returns whether the integration permits multiple connections to be established.
     *
     * @return boolean
     */
    public function allowsMultipleConnections(): bool {
        return $this->allowMultipleConnections;
    }

    /**
     * Returns the connections associated with this integration.
     *
     * @param string|bool $forEnvironment The environment for which to retrieve connections. If empty, retrieves connections for all environments. Default is an empty string.
     * @param bool        $refresh        Whether to refresh the connections from the database. Default is false.
     *
     * @return array<ExternalConnection> An array of ExternalConnection instances associated with this integration.
     */
    public function getConnections(string|bool $forEnvironment = 'current', bool $refresh = false): array {
        if (is_bool($forEnvironment)) {
            $refresh = $forEnvironment;
            $forEnvironment = 'current';
        }

        if ($forEnvironment === 'current') {
            $forEnvironment = $this->getCurrentEnvironment($refresh) ?? '';
        }

        if ($refresh || empty($this->connections)) {
            if (!empty($forEnvironment) && $this->hasMultipleEnvironments()) {
                $this->connections = ExternalConnection::where('integration_id', $this->handle)
                    ->where('environment', $forEnvironment)
                    ->orderBy('connected_at', 'desc')
                    ->get()
                    ->all();
            } else {
                $this->connections = ExternalConnection::where('integration_id', $this->handle)
                    ->orderBy('connected_at', 'desc')
                    ->get()
                    ->all();
            }
        }

        return $this->connections;
    }

    /**
     * Returns a specific connection associated with this integration by its label.
     *
     * @param string      $label          The label of the connection to retrieve.
     * @param string|bool $forEnvironment The environment for which to retrieve the connection. If empty, retrieves the connection for all environments. Default is 'current'.
     * @param bool        $refresh        Whether to refresh the connections from the database. Default is false.
     *
     * @return ExternalConnection|null The ExternalConnection instance with the specified label, or null if not found.
     */
    public function getConnection(string $label, string|bool $forEnvironment = 'current', bool $refresh = false): ?ExternalConnection {
        if (is_bool($forEnvironment)) {
            $refresh = $forEnvironment;
            $forEnvironment = 'current';
        }

        $connections = collect($this->getConnections($forEnvironment, $refresh));

        if ($connections->isNotEmpty()) {
            return $connections->firstWhere('label', $label);
        }

        return null;
    }

    /**
     * Returns whether this integration has any connections.
     *
     * @param string|bool $forEnvironment The environment for which to check connections. If empty, checks connections for all environments. Default is 'current'.
     * @param bool        $refresh       Whether to refresh the connections from the database. Default is false.
     *
     * @return bool True if there are any connections associated with this integration, false otherwise.
     */
    public function hasConnections(string|bool $forEnvironment = 'current', bool $refresh = false): bool {
        if (is_bool($forEnvironment)) {
            $refresh = $forEnvironment;
            $forEnvironment = 'current';
        }

        return !empty($this->getConnections($forEnvironment, $refresh));
    }

    /**
     * Returns whether this integration has a connection with the specified label.
     *
     * @param string      $label          The label of the connection to check for.
     * @param string|bool $forEnvironment The environment for which to check connections. If empty, checks connections for all environments. Default is 'current'.
     * @param bool        $refresh        Whether to refresh the connections from the database. Default is false.
     *
     * @return bool True if a connection with the specified label exists, false otherwise.
     */
    public function hasConnection(string $label, string|bool $forEnvironment = 'current', bool $refresh = false): bool {
        if (is_bool($forEnvironment)) {
            $refresh = $forEnvironment;
            $forEnvironment = 'current';
        }

        return $this->getConnection($label, $forEnvironment, $refresh) !== null;
    }

    /**
     * Returns whether this integration has multiple connections.
     *
     * @param string|bool $forEnvironment The environment for which to check connections. If empty, checks connections for all environments. Default is 'current'.
     * @param bool        $refresh        Whether to refresh the connections from the database. Default is false.
     *
     * @return bool True if there are multiple connections associated with this integration, false otherwise.
     */
    public function hasMultipleConnections(string|bool $forEnvironment = 'current', bool $refresh = false): bool {
        if (is_bool($forEnvironment)) {
            $refresh = $forEnvironment;
            $forEnvironment = 'current';
        }
        return count($this->getConnections($forEnvironment, $refresh)) > 1;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds a request URL for the integration, replacing any variables in the endpoint with their corresponding values from the integration's settings.
     *
     * @param string $endpoint The endpoint to build the request URL for.
     * @param array  $queryParams Optional query parameters to append to the URL.
     *
     * @return string The built request URL.
     */
    protected function buildRequestUrl(string $endpoint, array $queryParams = []): string {
        $hasVariables = Str::contains($endpoint, '{');

        if ($hasVariables) {
            $segments = explode('/', $endpoint);
            $segments = array_map(function ($value) {
                $value = $this->resolveDynamicValue($value);
                return $this->sanitizeDynamicValue($value);
            }, $segments);

            $endpoint = implode('/', $segments);
        }

        if (!empty($queryParams)) {
            $queryParams = array_map(function ($value) {
                $value = $this->resolveDynamicValue($value);
                return $this->sanitizeDynamicValue($value);

            }, $queryParams);

            $endpoint .= '?' . http_build_query($queryParams);
        }

        return $endpoint;
    }

    /**
     * Builds the request payload for token requests based on the provided keys and additional parameters.
     *
     * @param array $payloadKeys The keys to include in the payload.
     * @param array $overrides   Parameters to override default settings.
     * 
     * @return array The constructed request payload.
     */
    protected function buildRequestPayload(array $payloadKeys, array $overrides = []): array {
        $payload = [];

        foreach ($payloadKeys as $key => $value) {
            if (isset($overrides[$key])) {
                $payload[$key] = $overrides[$key];
            } else {
                $payload[$key] = $this->sanitizeDynamicValue($this->resolveDynamicValue($value));
            }
        }

        return $payload;
    }

    /**
     * Resolves a dynamic value by checking if it is a string that starts and ends with curly braces, indicating that it is a variable. If it is, the method retrieves the corresponding setting value for that variable.
     *
     * @param mixed $value The value to resolve.
     *
     * @return mixed The resolved value, or the original value if it is not a dynamic variable.
     */
    protected function resolveDynamicValue(mixed $value): mixed {
        if (is_string($value) && Str::startsWith($value, '{') && Str::endsWith($value, '}')) {
            $variableName = trim($value, '{}');
            return $this->settings($variableName, 'current', true);
        }

        return $value;
    }

    /**
     * Sanitizes a dynamic value by converting it to a string representation. If the value is a boolean, it is converted to 'true' or 'false'. If the value is null, it is converted to an empty string. If the value is an array or object, it is converted to a JSON string. Otherwise, the value is cast to a string.
     *
     * @param mixed $value The value to sanitize.
     *
     * @return string The sanitized string representation of the value.
     */
    protected function sanitizeDynamicValue(mixed $value): string {
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
}