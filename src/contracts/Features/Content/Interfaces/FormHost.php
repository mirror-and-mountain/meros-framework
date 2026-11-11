<?php

namespace MM\Meros\Contracts\Features\Content\Interfaces;

use Closure;
use MM\Meros\App\Models\Post;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\Contracts\Features\Components\Field;

interface FormHost {
    public function getProvider(): FeatureProvider;
    public function getHandle(string $format = 'default'): string;
    public function getFormStructure(?int $postId = null): array;
    public function getFormStructureMetaKey(): string;
    public function handleSubmission(int $id, array|string $data): array;
    public function findField(Post $post, string $name, string $originalName, ?string $fieldClass = null): ?Field;
    public function getStoredFields(int $postId, array $config = []): ?array;
    public function metaBox(string $id, string $title, Closure $callback, string $context = 'advanced', string $priority = 'default'): void;

}