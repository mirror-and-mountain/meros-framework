<?php

namespace MM\Meros\Support\Integrations;

use Illuminate\Support\Str;

/**
 * A state store for managing OAuth state values.
 *
 * This class provides methods to issue and consume OAuth state values, which are used to prevent CSRF attacks
 * during the OAuth authorization flow. The state values are stored either in WordPress transients or in-memory
 * for non-WordPress environments (e.g., unit tests).
 */
final class OAuthStateStore {
    /**
     * In-memory fallback used outside WordPress runtime (for example unit tests).
     *
     * @var array<string, array{payload: array, expires_at: int}>
     */
    private static array $memory = [];

    /**
     * Issues a new OAuth state value and stores the associated payload.
     *
     * @param array $payload The payload to associate with the issued state.
     * @param int   $ttlSeconds The time-to-live (TTL) in seconds for the state value. Default is 600 seconds (10 minutes).
     *
     * @return string The issued state value.
     */
    public function issue(array $payload, int $ttlSeconds = 600): string {
        $state = Str::random(64);
        $key = $this->key($state);

        if (function_exists('set_transient')) {
            set_transient($key, $payload, $ttlSeconds);

            return $state;
        }

        self::$memory[$key] = [
            'payload'    => $payload,
            'expires_at' => time() + $ttlSeconds,
        ];

        return $state;
    }

    /**
     * Consumes an OAuth state value and retrieves the associated payload.
     *
     * @param string $state The state value to consume.
     *
     * @return array|null The associated payload if the state is valid and not expired; otherwise, null.
     */
    public function consume(string $state): ?array {
        $key = $this->key($state);

        if (function_exists('get_transient') && function_exists('delete_transient')) {
            $payload = get_transient($key);
            delete_transient($key);

            return is_array($payload) ? $payload : null;
        }

        $entry = self::$memory[$key] ?? null;

        if (!is_array($entry)) {
            return null;
        }

        unset(self::$memory[$key]);

        if (($entry['expires_at'] ?? 0) < time()) {
            return null;
        }

        $payload = $entry['payload'] ?? null;

        return is_array($payload) ? $payload : null;
    }

    /**
     * Generates a unique key for storing the state value.
     *
     * @param string $state The state value.
     *
     * @return string The generated key.
     */
    private function key(string $state): string {
        return 'meros_integration_oauth_state_' . hash('sha256', $state);
    }
}
