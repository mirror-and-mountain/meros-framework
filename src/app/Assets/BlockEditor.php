<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class BlockEditor extends AssetGroup {
    protected function configure(): void {
        $this->add([
            [
                'handle' => 'meros-block-editor',
                'path' => 'editor/index.js',
                'dependencies' => ['group_meros_forms_assets'],
            ],
        ], ['editor']);

        $this->name('meros-block-editor-helpers');
        $this->description('Meros Block Editor Helpers');
    }
}