<?php

namespace MM\Meros\Services\Registers;

use Illuminate\Support\Collection;

use MM\Meros\Services\Contracts\Integration;
use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\FeatureProvider;

class Integrations extends Register {
    protected string $identifier = 'handle';

    protected string $definition = Integration::class;

    protected array $supports = [
        'register',
        'make',
        'makeFrom',
        'makeFromCallback',
        'public',
        'multiple'
    ];

    protected function parseProperties(array $props): array {
        return [
            'handle'           => $props['handle'] ?? '',
            'label'            => $props['label'] ?? '',
            'description'      => $props['description'] ?? '',
            'category'         => $props['category'] ?? 'general',
            'authType'         => $props['authType'] ?? $props['auth_type'] ?? 'api_key',
            'baseUri'          => $props['baseUri'] ?? $props['base_uri'] ?? '',
            'apiVersion'       => $props['apiVersion'] ?? $props['api_version'] ?? '',
            'scopes'           => $props['scopes'] ?? [],
            'config'           => $props['config'] ?? [],
            'switchable'       => $props['switchable'] ?? true,
            'enabledByDefault' => $props['enabledByDefault'] ?? false,
        ];
    }

    /**
     * Registers multiple integrations in one call.
     *
     * @param array<string, string|array|\Closure> $integrations
     *
     * @return void
     */
    public function registerMany(array $integrations): void {
        $this->ensureCheckedOut();
        $provider = $this->provider;

        foreach ($integrations as $handle => $integration) {
            $this->register($handle, $integration, $provider);
        }
    }

    /**
     * Resolves all registered integrations into concrete instances.
     *
     * @param FeatureProvider|null $provider
     *
     * @return Collection<int, Integration>
     */
    public function allResolved(?FeatureProvider $provider = null): Collection {
        $this->ensureCheckedOut($provider);

        $items = collect($this->getRegistered())
            ->map(function ($registered, $handle) {
                if ($registered instanceof \Closure) {
                    return $this->makeFrom($handle);
                }

                return $this->makeFrom($handle);
            })
            ->values();

        $this->checkin();

        return $items;
    }
}