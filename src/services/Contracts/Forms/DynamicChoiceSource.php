<?php

namespace MM\Meros\Services\Contracts\Forms;

use Closure;
use MM\Meros\Services\Contracts\FeatureDefinition;

class DynamicChoiceSource extends FeatureDefinition {
    /**
     * Unique source key used by dynamic choice fields.
     *
     * @var string
     */
    public string $source = '';

    /**
     * Human readable source label shown in the builder.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * Optional source description shown in the builder.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * Optional resolver callback for building option payloads.
     *
     * @var Closure|null
     */
    protected ?Closure $resolver = null;

    /**
     * Source-specific configuration fields rendered in the builder.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $configFields = [];

    protected function queue(): void {
        $this->queued = true;
    }

    public function source(string $source): self {
        $this->source = trim($source);

        return $this;
    }

    public function label(string $label): self {
        $this->label = trim($label);

        return $this;
    }

    public function description(string $description): self {
        $this->description = trim($description);

        return $this;
    }

    public function resolver(callable|Closure $resolver): self {
        $resolved = $this->convertToClosure($resolver);

        if ($resolved !== false) {
            $this->resolver = $resolved;
        }

        return $this;
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    public function configFields(array $fields): self {
        $this->configFields = array_values(array_filter($fields, fn ($field) => is_array($field)));

        return $this;
    }

    /**
     * @return array<int, array{value:string,text:string}>
     */
    public function resolve(\WP_REST_Request $request): array {
        if (!$this->isAvailable()) {
            return [];
        }

        if (!$this->resolver instanceof Closure) {
            return [];
        }

        $result = ($this->resolver)($request, $this);

        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    public function getLabel(): string {
        return $this->label !== '' ? $this->label : $this->source;
    }

    public function getDescription(): string {
        return $this->description;
    }

    /**
     * Returns whether this source is currently available.
     */
    public function isAvailable(): bool {
        return true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConfigFields(): array {
        return $this->configFields;
    }

    /**
     * @return array<string, mixed>
     */
    public function toBuilderDefinition(): array {
        return [
            'source' => $this->source,
            'label' => $this->getLabel(),
            'description' => $this->description,
            'configFields' => $this->configFields,
        ];
    }
}
