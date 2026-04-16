<?php 

namespace MM\Meros\App\Contracts;

interface FieldRenderer {
    public function configure(array $config): self;

    public function required(bool $required = true): self;

    public function disabled(bool $disabled = true): self;

    public function class(string|array $classes): self;

    public function render(): void;
}