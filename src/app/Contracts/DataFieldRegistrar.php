<?php

namespace MM\Meros\App\Contracts;

use MM\Meros\App\Support\Fields\DataField;

interface DataFieldRegistrar {
    public function field(
        ?string  $type   = null,
        array    $config = [],
        array    $args   = []
    ): DataField;
    
    public function getFieldID(): string;
    public function getFieldName(): string;
    public function getFieldLabel(): string;
    public function getFieldDescription(): string;
    public function getValue(): mixed;

    public function getItemNames(): array;
    public function getFieldNames(): array;
    public function getItemLabels(): array;
    public function getItemByName(string $name): ?DataFieldRegistrar;
}