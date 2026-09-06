<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\Asset;

final class DynamicBlock extends Asset {
    protected function configure(): void {
        $this->path('editor/meros-dynamic-block/index.js', true);
        $this->handle('meros-dynamic-block');
        $this->usesAjax([
            'blocks' => []
        ]);
    }
}