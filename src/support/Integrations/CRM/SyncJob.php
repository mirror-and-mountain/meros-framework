<?php

namespace MM\Meros\Support\Integrations\CRM;

final class SyncJob {
    public function __construct(
        public readonly string $integrationHandle,
        public readonly string $object,
        public readonly string $method = 'POST',
        public readonly string $endpoint = '',
        public readonly string $connectionLabel = '',
        public readonly string $environment = '',
        public readonly array $mappings = [],
        public readonly array $metadata = [],
    ) {
    }

    public static function fromArray(array $payload): self {
        return new self(
            integrationHandle: trim((string) ($payload['integration_handle'] ?? '')),
            object: trim((string) ($payload['object'] ?? $payload['object_name'] ?? '')),
            method: strtoupper(trim((string) ($payload['method'] ?? 'POST'))),
            endpoint: trim((string) ($payload['endpoint'] ?? '')),
            connectionLabel: trim((string) ($payload['connection_label'] ?? '')),
            environment: trim((string) ($payload['environment'] ?? '')),
            mappings: is_array($payload['mappings'] ?? null) ? $payload['mappings'] : [],
            metadata: is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        );
    }

    public function isValid(): bool {
        return $this->integrationHandle !== '' && ($this->object !== '' || $this->endpoint !== '');
    }

    public function resolveEndpoint(): string {
        if ($this->endpoint !== '') {
            return ltrim($this->endpoint, '/');
        }

        return ltrim($this->object, '/');
    }
}
