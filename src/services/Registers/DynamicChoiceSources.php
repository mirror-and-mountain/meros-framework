<?php

namespace MM\Meros\Services\Registers;

use Illuminate\Support\Collection;
use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\Register;
use MM\Meros\Services\Contracts\Forms\DynamicChoiceSource;

class DynamicChoiceSources extends Register {
    protected string $identifier = 'source';

    protected string $definition = DynamicChoiceSource::class;

    protected array $supports = [
        'register',
        'make',
        'makeFrom',
        'public',
        'multiple',
    ];

    protected function parseProperties(array $props): array {
        return [
            'source' => $props['source'] ?? '',
            'label' => $props['label'] ?? '',
            'description' => $props['description'] ?? '',
            'resolver' => $props['resolver'] ?? null,
            'configFields' => $props['configFields'] ?? $props['config_fields'] ?? [],
        ];
    }

    /**
     * @return Collection<int, DynamicChoiceSource>
     */
    public function allResolved(?FeatureProvider $provider = null): Collection {
        $this->ensureCheckedOut($provider);
        $provider = $this->provider;

        $registered = collect($this->getRegistered())
            ->map(function ($_registered, $source) {
                return $this->makeFrom((string) $source);
            })
            ->filter(fn ($item) => $item instanceof DynamicChoiceSource)
            ->values();

        $this->checkout($provider);

        $instances = $this->get('');
        $this->checkin();

        if (!$instances instanceof Collection) {
            return $registered;
        }

        return $instances
            ->filter(fn ($item) => $item instanceof DynamicChoiceSource)
            ->merge($registered)
            ->unique('source')
            ->values();
    }
}
