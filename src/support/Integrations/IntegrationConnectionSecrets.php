<?php

namespace MM\Meros\Support\Integrations;

use MM\Meros\App\Models\IntegrationConnection;

final class IntegrationConnectionSecrets {
    public function __construct(
        protected IntegrationConnection $connection
    ) {
    }

    public function apiKey(): ?string {
        return $this->getSecret('api_key');
    }

    public function accessToken(): ?string {
        return $this->getSecret('access_token');
    }

    public function refreshToken(): ?string {
        return $this->getSecret('refresh_token');
    }

    public function idToken(): ?string {
        return $this->getSecret('id_token');
    }

    public function bearerToken(): ?string {
        return $this->accessToken() ?? $this->apiKey();
    }

    public function hasRefreshToken(): bool {
        return $this->refreshToken() !== null;
    }

    public function scopes(): array {
        $scopes = $this->connection->scopes ?? [];

        return is_array($scopes) ? $scopes : [];
    }

    public function metadata(string $key = '', mixed $default = null): mixed {
        $metadata = $this->connection->metadata ?? [];

        if ($key === '') {
            return is_array($metadata) ? $metadata : [];
        }

        return data_get($metadata, $key, $default);
    }

    public function isExpired(): bool {
        $expiresAt = $this->connection->token_expires_at;

        if ($expiresAt === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo($expiresAt);
    }

    protected function getSecret(string $key): ?string {
        $value = $this->connection->getAttribute($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}