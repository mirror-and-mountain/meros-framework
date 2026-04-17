<?php

namespace MM\Meros\Services\Contracts;

use MM\Meros\Services\Contracts\Field;

interface AdminFieldRegistrant {
    public function field(
        ?string  $type   = null,
        array    $config = [],
        array    $props   = []
    ): Field;
    
    public function getID(): string;
    public function getName(): string;
    public function getLabel(): string;
    public function getDescription(): string;
    public function getValue(): mixed;
    public function getDefault(): mixed;

    public function getItemNames(): array;
    public function getFieldNames(): array;
    public function getItemLabels(): array;
    public function getItemByName(string $name): ?AdminFieldRegistrant;
}