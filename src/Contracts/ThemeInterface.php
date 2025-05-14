<?php 

namespace MM\Meros\Contracts;

interface ThemeInterface
{
    public function addFeature(string $name, object $feature): void;
    public function getFeatures(): array;
    public function getFeature(string $name): object|null;

    public function getAuthorFeatures(string $author): ?array;

    public static function bootstrap(array $providers = []): void;
    public function initialise(): void;
}