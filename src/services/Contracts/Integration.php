<?php

namespace MM\Meros\Services\Contracts;

use Closure;

use MM\Meros\Services\Concerns\IsSwitchable;
use MM\Meros\Support\Integrations\ConfigurationBuilder;

abstract class Integration extends FeatureDefinition {
    public string $handle = '';

    protected string $label = '';

    protected string $description = '';

    protected string $category = 'general';

    protected string $authType = 'api_key';

    protected string $baseUri = '';

    protected string $apiVersion = '';

    protected array $config = [];

    protected array $scopes = [];

    protected array $configurationFields = [];

    use IsSwitchable;

    protected function queue(): void {
        $this->queued = true;
    }

    public function handle(string $handle): self {
        $this->handle = $handle;
        return $this;
    }

    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    public function category(string $category): self {
        $this->category = $category;
        return $this;
    }

    public function crm(): self {
        return $this->category('crm');
    }

    public function email(): self {
        return $this->category('email');
    }

    public function payments(): self {
        return $this->category('payments');
    }

    public function finance(): self {
        return $this->category('finance');
    }

    public function marketing(): self {
        return $this->category('marketing');
    }

    public function customCategory(string $category): self {
        return $this->category($category);
    }

    public function authType(string $authType): self {
        $this->authType = $authType;
        return $this;
    }

    public function oauth(): self {
        return $this->authType('oauth');
    }

    public function apiKey(): self {
        return $this->authType('api_key');
    }

    public function basic(): self {
        return $this->authType('basic');
    }

    public function token(): self {
        return $this->authType('token');
    }

    public function baseUri(string $baseUri): self {
        $this->baseUri = $baseUri;
        return $this;
    }

    public function apiVersion(string $apiVersion): self {
        $this->apiVersion = $apiVersion;
        return $this;
    }

    public function scopes(array $scopes): self {
        $this->scopes = $scopes;
        return $this;
    }

    public function withScopes(array $scopes): self {
        return $this->scopes($scopes);
    }

    public function config(array $config): self {
        $this->config = $config;
        return $this;
    }

    public function configuration(Closure $callback): self {
        $builder = new ConfigurationBuilder();
        $callback($builder);
        $this->configurationFields = $builder->all();

        return $this;
    }

    public function getConfigurationFields(): array {
        return $this->configurationFields;
    }

    public function withConfig(array $config): self {
        return $this->config($config);
    }

    public function withBaseUri(string $baseUri): self {
        return $this->baseUri($baseUri);
    }

    public function withApiVersion(string $apiVersion): self {
        return $this->apiVersion($apiVersion);
    }

    public function getHandle(): string {
        return $this->handle;
    }

    public function getLabel(): string {
        return $this->label;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getCategory(): string {
        return $this->category;
    }

    public function getAuthType(): string {
        return $this->authType;
    }

    public function getBaseUri(): string {
        return $this->baseUri;
    }

    public function getApiVersion(): string {
        return $this->apiVersion;
    }

    public function getScopes(): array {
        return $this->scopes;
    }

    public function getConfig(): array {
        return $this->config;
    }
}