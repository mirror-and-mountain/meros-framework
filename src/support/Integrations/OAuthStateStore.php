<?php

namespace MM\Meros\Support\Integrations;

use Illuminate\Support\Str;

final class OAuthStateStore {
    /**
     * In-memory fallback used outside WordPress runtime (for example unit tests).
     *
     * @var array<string, array{payload: array, expires_at: int}>
     */
    private static array $memory = [];

    public function issue(array $payload, int $ttlSeconds = 600): string {
        $state = Str::random(64);
        $key = $this->key($state);

        if (function_exists('set_transient')) {
            set_transient($key, $payload, $ttlSeconds);

            return $state;
        }

        self::$memory[$key] = [
            'payload' => $payload,
            'expires_at' => time() + $ttlSeconds,
        ];

        return $state;
    }

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

    private function key(string $state): string {
        return 'meros_integration_oauth_state_' . hash('sha256', $state);
    }
}
