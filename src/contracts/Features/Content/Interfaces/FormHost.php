<?php

namespace MM\Meros\Contracts\Features\Content\Interfaces;

use Closure;

use MM\Meros\App\Models\Post;
use MM\Meros\Contracts\Providers\FeatureProvider;

use MM\Meros\Contracts\Features\Components\Form as FormComponent;
use MM\Meros\Contracts\Features\Components\Field;

use MM\Meros\App\Models\FormTemplate;

interface FormHost {
    public function getProvider(): FeatureProvider;
    public function getHandle(string $format = 'default'): string;
    public function getFormStructure(?int $postId = null, bool $refresh = false): array;
    public function getFormActions(?int $postId = null, bool $refresh = false): array;
    public function getFormSettings(?int $postId = null, bool $refresh = false): array;
    public function getFormInstance(Post|int $post, array $rows = [], array $availableFields = [], bool $hideSubmitButton = false): FormComponent;
    public function getFormTemplateTitle(): string;
    public function getFormTemplateDescription(): string;
    public function getTemplateFields(): ?array;
    public function hasComponentFormTemplate(): bool;
    public function hasConfigurableFormTemplate(): bool;
    public function hasFormTemplate(): bool;
    public function getFormTemplate(): FormTemplate|FormComponent|null;
    public function getFormStructureMetaKey(): string;
    public function handleSubmission(int $id, array|string $data): array;
    public function findField(Post $post, string $name, string $originalName, ?string $fieldClass = null): ?Field;
    public function getStoredFields(int $postId, array $config = []): ?array;
    public function metaBox(string $id, string $title, Closure $callback, string $context = 'advanced', string $priority = 'default'): void;
    public function inspect(?int $id = null, bool $refresh = false): array;
}