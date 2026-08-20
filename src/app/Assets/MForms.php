<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class MForms extends AssetGroup {
    protected function configure(): void {
        $assets = [
            [
                'path' => 'mforms/index.js',
                'dependencies' => ['group_meros_forms_dependencies'],
            ],
            [
                'path' => 'mforms/style-index.css',
                'dependencies' => ['group_meros_forms_dependencies'],
            ]
        ];

        foreach (['site', 'admin'] as $context) {
            $this->assets[$context] = $assets;
        }

        $this->name('meros_forms_assets');
        $this->description('Assets registered by Meros for the forms.');
    }
}