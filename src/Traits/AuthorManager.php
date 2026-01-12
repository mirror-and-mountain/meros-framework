<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Arr;

trait AuthorManager {
    protected static string $authorName;

    protected static string $authorDesc;

    protected static string $authorUrl;

    protected static string $authorSupportUrl;

    /**
     * Helper to get features by author name.
     *
     * @param  string  $author
     */
    final public function getAuthorFeatures(string $authorName): ?array {
        $features = Arr::undot($this->features);

        return $features[$authorName] ?? null;
    }

    final public function getAuthorName(): string {
        return self::$authorName;
    }

    final public function getAuthorDesc(): string {
        return self::$authorDesc;
    }

    final public function getAuthorUrl(): string {
        return self::$authorUrl;
    }

    final public function getAuthorSupportUrl(): string {
        return self::$authorSupportUrl;
    }

    final public function getAuthorInfo(): array {
        return [
            'name'        => self::$authorName,
            'description' => self::$authorDesc,
            'url'         => self::$authorUrl,
            'support_url' => self::$authorSupportUrl,
        ];
    }
}
