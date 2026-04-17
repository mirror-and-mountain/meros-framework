<?php

namespace MM\Meros\App\Support\Helpers;

use Illuminate\Support\Str;

class PathResolver {
    public function __construct(
        protected string $root
    ) {}

    /**
     * Strip root prefix from a full path.
     * 
     * my_setting.foo.bar - foo.bar
     */
    public function stripRoot(string $path): string {
        if (Str::startsWith($path, $this->root . '.')) {
            return Str::after($path, $this->root . '.');
        }

        if ($path === $this->root) {
            return '';
        }

        return $path;
    }

    /**
     * Strip array root prefix.
     * 
     * my_setting.*.foo.bar - foo.bar
     */
    public function stripArrayRoot(string $path): string {
        return Str::after($path, "{$this->root}.*.");
    }

    /**
     * Convert a full path into segments.
     */
    public function segments(string $path): array {
        return explode('.', $path);
    }

    /**
     * Build a full path from root + relative.
     */
    public function build(string $relative): string {
        return trim("{$this->root}.{$relative}", '.');
    }

    /**
     * Determine if path is within a repeatable structure.
     */
    public function isRepeatable(string $path): bool {
        return Str::contains($path, '.*.');
    }

    /**
     * Normalize path (remove duplicate dots etc)
     */
    public function normalize(string $path): string {
        return preg_replace('/\.+/', '.', trim($path, '.'));
    }
}