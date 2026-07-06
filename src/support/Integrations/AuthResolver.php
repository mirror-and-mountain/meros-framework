<?php

namespace MM\Meros\Support\Integrations;

use MM\Meros\App\Models\IntegrationConnection;

/**
 * Class AuthResolver
 *
 * This class is responsible for resolving the appropriate authentication headers
 * for a given integration and its associated connection. It supports various
 * authentication types, including OAuth, API Key, and Basic Authentication.
 */
class AuthResolver {

    /**
     * Resolves the authentication headers for a given integration and connection.
     *
     * @param mixed $integration The integration instance or object.
     * @param IntegrationConnection $connection The associated integration connection.
     *
     * @return array<string, string> An associative array of HTTP headers for authentication.
     */
    public function resolve(
        mixed $integration,
        IntegrationConnection $connection
    ): array {
        $secrets = new IntegrationConnectionSecrets($connection);
        $authType = $this->resolveAuthType($integration);

        switch ($authType) {
            case 'oauth':
                $accessToken = $secrets->bearerToken();

                if ($accessToken === null) {
                    return [];
                }

                return [
                    'Authorization' => 'Bearer ' . $accessToken,
                ];

            case 'api_key':
                $apiKey = $secrets->apiKey();

                if ($apiKey === null) {
                    return [];
                }

                return [
                    'Authorization' => 'Bearer ' . $apiKey,
                ];

            case 'basic':
                $username = $secrets->metadata('username');
                $password = $secrets->metadata('password');

                if (!is_string($username) || !is_string($password) || $username === '' || $password === '') {
                    return [];
                }

                return [
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                ];
        }

        return [];
    }

    /**
     * Determines the authentication type for a given integration.
     *
     * @param mixed $integration The integration instance or object.
     *
     * @return string The resolved authentication type (e.g., 'oauth', 'api_key', 'basic').
     */
    protected function resolveAuthType(mixed $integration): string {
        if (is_object($integration)) {
            if (method_exists($integration, 'getAuthType')) {
                return (string) $integration->getAuthType();
            }

            if (isset($integration->auth_type) && is_string($integration->auth_type)) {
                return $integration->auth_type;
            }

            if (isset($integration->authType) && is_string($integration->authType)) {
                return $integration->authType;
            }
        }

        return 'api_key';
    }
}