<?php 

namespace MM\Meros\Contracts;

interface FeatureManager
{
    public function addFeature(string $name, string $category, string|callable $bootstrapper, array|string $author, array $args = []): bool;
    public function getFeatures(): array;
    public function getFeature(string $name): string|object|null;

    public function addAuthor(string|array $author): bool|string;
    public function getAuthors(): array;
    public function getAuthor(string $name): ?array;
    public function getAuthorFeatures(string $author): ?array;

    public function bootstrap();
}