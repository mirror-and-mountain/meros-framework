<?php 

namespace MM\Meros\App\Support;

class StructuredPostType extends PostType {
    protected array $schema = [];

    protected function defaults(): array {
        return [
            'use_block_editor' => false,
        ];
    }
}