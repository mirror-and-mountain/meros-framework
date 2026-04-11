<?php

namespace MM\Meros\App\Contracts;

use Closure;
use MM\Meros\App\Support\Field;

interface FieldRegistrar {
    public function field(
        ?string  $type = null,
        mixed    $config = null,
        ?Closure $callback = null,
        array    $args = []
    ): Field;
    
    public function getType(): ?string;
    public function getItemType(): ?string;
    public function getID(): string;
    public function getName(): string;
    public function getLabel(): string;
    public function getDescription(): string;
    public function getValue(): mixed;
}