<?php

namespace MM\Meros\Support\Integrations;

use MM\Meros\App\Models\IntegrationConnection;

/**
 * Class IntegrationConnectionSecrets
 *
 * This class provides a secure way to access sensitive information related to an integration connection.
 * It encapsulates the retrieval of secrets such as API keys, access tokens, refresh tokens, and other metadata.
 */
final class IntegrationConnectionSecrets {
    public function __construct(protected IntegrationConnection $connection) {
        // Constructor to initialise the IntegrationConnectionSecrets with a specific IntegrationConnection instance.
    }

    /**
     * Retrieves the API key associated with the integration connection.
     *
     * @return string|null The API key, or null if not set.
     */
    public function apiKey(): ?string {
        return $this->getSecret('api_key');
    }

    /**
     * Retrieves the access token associated with the integration connection.
     *
     * @return string|null The access token, or null if not set.
     */
    public function accessToken(): ?string {
        return $this->getSecret('access_token');
    }

    /**
     * Retrieves the refresh token associated with the integration connection.
     *
     * @return string|null The refresh token, or null if not set.
     */
    public function refreshToken(): ?string {
        return $this->getSecret('refresh_token');
    }

    /**
     * Retrieves the ID token associated with the integration connection.
     *
     * @return string|null The ID token, or null if not set.
     */
    public function idToken(): ?string {
        return $this->getSecret('id_token');
    }

    /**
     * Retrieves the bearer token for the integration connection.
     * It first checks for an access token, and if not available, falls back to the API key.
     *
     * @return string|null The bearer token, or null if neither is set.
     */
    public function bearerToken(): ?string {
        return $this->accessToken() ?? $this->apiKey();
    }

    /**
     * Checks if the integration connection has a refresh token.
     *
     * @return bool True if a refresh token exists, false otherwise.
     */
    public function hasRefreshToken(): bool {
        return $this->refreshToken() !== null;
    }

    /**
     * Retrieves the scopes associated with the integration connection.
     *
     * @return array The scopes as an array of strings.
     */
    public function scopes(): array {
        $scopes = $this->connection->scopes ?? [];

        return is_array($scopes) ? $scopes : [];
    }

    /**
     * Retrieves metadata associated with the integration connection.
     *
     * @param string $key The specific metadata key to retrieve.
     * @param mixed $default The default value to return if the key does not exist.
     * 
     * @return mixed The metadata value, or the default value if the key is not found.
     */
    public function metadata(string $key = '', mixed $default = null): mixed {
        $metadata = $this->connection->metadata ?? [];

        if ($key === '') {
            return is_array($metadata) ? $metadata : [];
        }

        return data_get($metadata, $key, $default);
    }

    /**
     * Determines if the integration connection is expired based on its token expiration time.
     *
     * @return bool True if the connection is expired, false otherwise.
     */
    public function isExpired(): bool {
        $expiresAt = $this->connection->token_expires_at;

        if ($expiresAt === null) {
            return false;
        }

        return now()->greaterThanOrEqualTo($expiresAt);
    }

    /**
     * Retrieves a secret value from the integration connection's attributes.
     *
     * @param string $key The key of the secret to retrieve.
     * 
     * @return string|null The secret value, or null if not set or empty.
     */
    protected function getSecret(string $key): ?string {
        $value = $this->connection->getAttribute($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}