<?php

namespace MM\Meros\Services\Contracts;

use Closure;

use MM\Meros\Services\Concerns\IsSwitchable;
use MM\Meros\Support\Integrations\ConfigurationBuilder;

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
     * The base URI for the integration's API.
     *
     * @var string
     */
    protected string $baseUri = '';

    /**
     * The API version for the integration.
     *
     * @var string
     */
    protected string $apiVersion = '';

    /**
     * The configuration for the integration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * The scopes for the integration.
     *
     * @var array
     */
    protected array $scopes = [];

    /**
     * The configuration fields for the integration.
     *
     * @var array
     */
    protected array $configurationFields = [];

    use IsSwitchable;

    /***************************
     * Contract methods
     ***************************/

    protected function queue(): void {
        $this->queued = true;
    }

    /***************************
     * Setters
     ***************************/

    /**
     * Set the handle for the integration.
     *
     * @param string $handle
     * @return self
     */
    public function handle(string $handle): self {
        $this->handle = $handle;
        return $this;
    }

    /**
     * Set the label for the integration.
     *
     * @param string $label
     * @return self
     */
    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Set the description for the integration.
     *
     * @param string $description
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Set the category for the integration.
     *
     * @param string $category
     *
     * @return self
     */
    public function category(string $category): self {
        $this->category = $category;
        return $this;
    }

    /**
     * Shortcut method to set the category to 'crm'.
     *
     * @return self
     */
    public function crm(): self {
        return $this->category('crm');
    }

    /**
     * Shortcut method to set the category to 'email'.
     *
     * @return self
     */
    public function email(): self {
        return $this->category('email');
    }

    /**
     * Shortcut method to set the category to 'payments'.
     *
     * @return self
     */
    public function payments(): self {
        return $this->category('payments');
    }

    /**
     * Shortcut method to set the category to 'finance'.
     *
     * @return self
     */
    public function finance(): self {
        return $this->category('finance');
    }

    /**
     * Shortcut method to set the category to 'marketing'.
     *
     * @return self
     */
    public function marketing(): self {
        return $this->category('marketing');
    }

    /**
     * Shortcut method to set the category to a custom value.
     *
     * @param string $category
     * @return self
     */
    public function customCategory(string $category): self {
        return $this->category($category);
    }

    /**
     * Sets the authentication type for the integration.
     * 
     * @param string $authType The authentication type (e.g., 'api_key', 'oauth', 'basic', 'token').
     * @return self
     */
    public function authType(string $authType): self {
        $this->authType = $authType;
        return $this;
    }

    /**
     * Shortcut method to set the authentication type to 'oauth'.
     *
     * @return self
     */
    public function oauth(): self {
        return $this->authType('oauth');
    }

    /**
     * Shortcut method to set the authentication type to 'api_key'.
     * 
     * @return self
     */
    public function apiKey(): self {
        return $this->authType('api_key');
    }

    /**
     * Shortcut method to set the authentication type to 'basic'.
     *
     * @return self
     */
    public function basic(): self {
        return $this->authType('basic');
    }

    /**
     * Shortcut method to set the authentication type to 'token'.
     *
     * @return self
     */
    public function token(): self {
        return $this->authType('token');
    }

    /**
     * Sets the base URI for the integration's API.
     *
     * @param string $baseUri
     *
     * @return self
     */
    public function baseUri(string $baseUri): self {
        $this->baseUri = $baseUri;
        return $this;
    }

    /**
     * Alias for the `baseUri` method to set the base URI for the integration's API.
     *
     * @param string $baseUri
     *
     * @return self
     */
    public function withBaseUri(string $baseUri): self {
        return $this->baseUri($baseUri);
    }

    /**
     * Sets the API version for the integration.
     *
     * @param string $apiVersion
     *
     * @return self
     */
    public function apiVersion(string $apiVersion): self {
        $this->apiVersion = $apiVersion;
        return $this;
    }

    /**
     * Alias for the `apiVersion` method to set the API version for the integration.
     *
     * @param string $apiVersion
     *
     * @return self
     */
    public function withApiVersion(string $apiVersion): self {
        return $this->apiVersion($apiVersion);
    }
    
    /**
     * Sets the scopes for the integration.
     *
     * @param array $scopes
     *
     * @return self
     */
    public function scopes(array $scopes): self {
        $this->scopes = $scopes;
        return $this;
    }

    /**
     * Alias for the `scopes` method to set the scopes for the integration.
     *
     * @param array $scopes
     *
     * @return self
     */
    public function withScopes(array $scopes): self {
        return $this->scopes($scopes);
    }

    /**
     * Sets the configuration for the integration.
     *
     * @param array $config
     *
     * @return self
     */
    public function config(array $config): self {
        $this->config = $config;
        return $this;
    }

    /**
     * Alias for the `config` method to set the configuration for the integration.
     *
     * @param array $config
     *
     * @return self
     */
    public function withConfig(array $config): self {
        return $this->config($config);
    }

    /**
     * Creates a configuration builder for the integration, allowing for the definition of configuration fields.
     *
     * @param Closure $callback
     *
     * @return self
     */
    public function configuration(Closure $callback): self {
        $builder = new ConfigurationBuilder();
        $callback($builder);
        $this->configurationFields = $builder->all();

        return $this;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Returns the configuration fields for the integration.
     *
     * @return array
     */
    public function getConfigurationFields(): array {
        return $this->configurationFields;
    }

    /**
     * Returns the unique handle for the integration.
     *
     * @return string
     */
    public function getHandle(): string {
        return $this->handle;
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
     * Returns the base URI for the integration's API.
     *
     * @return string
     */
    public function getBaseUri(): string {
        return $this->baseUri;
    }

    /**
     * Returns the API version for the integration.
     *
     * @return string
     */
    public function getApiVersion(): string {
        return $this->apiVersion;
    }

    /**
     * Returns the scopes for the integration.
     *
     * @return array
     */
    public function getScopes(): array {
        return $this->scopes;
    }

    /**
     * Returns the configuration for the integration.
     *
     * @return array
     */
    public function getConfig(): array {
        return $this->config;
    }
}