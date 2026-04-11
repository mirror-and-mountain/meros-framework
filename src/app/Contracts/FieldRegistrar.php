<?php

namespace MM\Meros\App\Contracts;

use Closure;
use MM\Meros\App\Support\Field;

interface FieldRegistrar {
    public function withField(string $type = '', ?Closure $callback = null): Field;
    public function getID(): string;
    public function getName(): string;
    public function getLabel(): string;
    public function getDescription(): string;
    public function getValue(): mixed;
}