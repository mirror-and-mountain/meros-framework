<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class BlockEditor extends AssetGroup {
    protected function configure(): void {
        $assets = [
            'meros-block-editor'  => 'editor/index.js'
        ];

        foreach ($assets as $handle => $path) {
            $this->add($path, $handle, ['editor']);
        }

        $this->name('meros-block-editor-helpers');
        $this->description('Meros Block Editor Helpers');
    }
}