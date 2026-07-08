<?php

namespace MM\Meros\Support\Concerns;

use MM\Meros\Support\Integrations\ExternalModelCache;

/**
 * Optional cache helpers for external model subclasses.
 */
trait UsesCaching {
    /**
     * Wraps a callback with cache lookup/storage.
     *
     * @param string $scope
     * @param callable $callback
     * @param array<string, mixed> $context
     */
    protected function rememberCache(string $scope, callable $callback, array $context = [], ?int $ttlSeconds = null): mixed {
        if (!$this->cacheEnabled()) {
            return $callback();
        }

        $ttl = $ttlSeconds ?? $this->cacheTtlSeconds();

        if ($ttl <= 0) {
            return $callback();
        }

        $store = $this->cacheStore();
        $cacheFactory = app('cache');
        $cache = $store === null ? $cacheFactory->store() : $cacheFactory->store($store);
        $key = $this->cacheKey($scope, $context);

        ExternalModelCache::track($key, $store, (string) ($this->integrationHandle ?? ''));

        return $cache->remember(
            $key,
            now()->addSeconds($ttl),
            $callback
        );
    }

    /**
     * Removes a cache entry for a specific scope/context.
     *
     * @param string $scope
     * @param array<string, mixed> $context
     */
    protected function forgetCache(string $scope, array $context = []): bool {
        $store = $this->cacheStore();
        $cacheFactory = app('cache');
        $cache = $store === null ? $cacheFactory->store() : $cacheFactory->store($store);
        $key = $this->cacheKey($scope, $context);

        ExternalModelCache::untrack($key, $store);

        return $cache->forget($key);
    }

    /**
     * Builds a stable key including model/integration context.
     *
     * @param array<string, mixed> $context
     */
    protected function cacheKey(string $scope, array $context = []): string {
        ksort($context);

        return implode(':', [
            $this->cachePrefix(),
            sha1(json_encode([
                'class' => static::class,
                'scope' => $scope,
                'integration' => $this->integrationHandle ?? '',
                'environment' => $this->environment ?? '',
                'connection' => $this->connectionLabel ?? '',
                'path' => $this->path ?? '',
                'context' => $context,
            ])),
        ]);
    }

    protected function cacheEnabled(): bool {
        return true;
    }

    protected function cacheTtlSeconds(): int {
        return 900;
    }

    protected function cachePrefix(): string {
        return 'meros:external-model';
    }

    protected function cacheStore(): ?string {
        return null;
    }
}
