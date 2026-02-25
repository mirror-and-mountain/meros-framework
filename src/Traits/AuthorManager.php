<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Arr;

trait AuthorManager {
    /**
     * The theme author's name.
     *
     * @var string
     */
    protected static string $authorName;

    /**
     * The theme author's description.
     *
     * @var string
     */
    protected static string $authorDesc;

    /**
     * The theme author's URL.
     *
     * @var string
     */
    protected static string $authorUrl;

    /**
     * The theme author's support URL.
     *
     * @var string
     */
    protected static string $authorSupportUrl;

    /**
     * Helper to get features by author name.
     *
     * @param string $authorName
     * @return array|null
     */
    final public function getAuthorFeatures(string $authorName): ?array {
        $features = Arr::undot($this->features);

        return $features[$authorName] ?? null;
    }

    /**
     * Returns the theme author's name.
     *
     * @return string
     */
    final public function getAuthorName(): string {
        return self::$authorName;
    }

    /**
     * Returns the theme author's description.
     *
     * @return string
     */
    final public function getAuthorDesc(): string {
        return self::$authorDesc;
    }

    /**
     * Returns the theme author's URL.
     *
     * @return string
     */
    final public function getAuthorUrl(): string {
        return self::$authorUrl;
    }

    /**
     * Returns the theme author's support URL.
     *
     * @return string
     */
    final public function getAuthorSupportUrl(): string {
        return self::$authorSupportUrl;
    }

    /**
     * Returns an array of the theme author's information.
     *
     * @return array
     */
    final public function getAuthorInfo(): array {
        return [
            'name'        => self::$authorName,
            'description' => self::$authorDesc,
            'url'         => self::$authorUrl,
            'support_url' => self::$authorSupportUrl,
        ];
    }
}
