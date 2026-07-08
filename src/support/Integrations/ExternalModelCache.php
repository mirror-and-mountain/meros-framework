<?php

namespace MM\Meros\Support\Integrations;

/**
 * Tracks and clears cache keys used by ExternalModel caching helpers.
 */
final class ExternalModelCache {
    /**
     * @var string
     */
    private const INDEX_OPTION = 'meros_external_model_cache_index';

    /**
     * In-memory fallback for non-WordPress runtimes.
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $memoryIndex = [];

    /**
     * Tracks a cache key so it can be invalidated later by integration lifecycle events.
     */
    public static function track(string $key, ?string $store = null, string $integrationHandle = ''): void {
        $key = trim($key);

        if ($key === '') {
            return;
        }

        $index = self::loadIndex();
        $index[self::entryId($key, $store)] = [
            'key' => $key,
            'store' => $store,
            'integration' => sanitize_key($integrationHandle),
        ];

        self::saveIndex($index);
    }

    /**
     * Removes a cache key from the tracked index.
     */
    public static function untrack(string $key, ?string $store = null): void {
        $entryId = self::entryId($key, $store);
        $index = self::loadIndex();

        if (!array_key_exists($entryId, $index)) {
            return;
        }

        unset($index[$entryId]);
        self::saveIndex($index);
    }

    /**
     * Clears tracked cache keys for one or more integrations.
     *
     * @param array<int, string> $integrationHandles
     */
    public static function clearByIntegration(array $integrationHandles = []): int {
        $index = self::loadIndex();

        if ($index === []) {
            return 0;
        }

        $handles = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => sanitize_key((string) $value),
            $integrationHandles
        ))));

        $clearAll = $handles === [];
        $cacheFactory = app('cache');
        $cleared = 0;

        foreach ($index as $entryId => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $integration = sanitize_key((string) ($entry['integration'] ?? ''));

            if (!$clearAll && !in_array($integration, $handles, true)) {
                continue;
            }

            $key = trim((string) ($entry['key'] ?? ''));
            $store = $entry['store'] ?? null;
            $store = is_string($store) && trim($store) !== '' ? trim($store) : null;

            if ($key !== '') {
                $cache = $store === null ? $cacheFactory->store() : $cacheFactory->store($store);
                $cache->forget($key);
                $cleared++;
            }

            unset($index[$entryId]);
        }

        self::saveIndex($index);

        return $cleared;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function loadIndex(): array {
        if (function_exists('get_option')) {
            $value = get_option(self::INDEX_OPTION, []);
            return is_array($value) ? $value : [];
        }

        return self::$memoryIndex;
    }

    /**
     * @param array<string, array<string, mixed>> $index
     */
    private static function saveIndex(array $index): void {
        if (function_exists('update_option')) {
            update_option(self::INDEX_OPTION, $index, false);
            return;
        }

        self::$memoryIndex = $index;
    }

    private static function entryId(string $key, ?string $store): string {
        return sha1((string) $store . '|' . $key);
    }
}
