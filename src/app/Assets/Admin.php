<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class Admin extends AssetGroup {
    protected function configure(): void {
        $assets = [
            [
                'path' => 'admin/index.js',
                'dependencies' => ['group_meros_forms_assets'],
            ],
            [
                'path' => 'admin/style-index.css',
                'dependencies' => ['group_meros_forms_assets'],
            ]
        ];

        $this->add($assets, ['admin']);
        $this->name('meros_admin_assets');
        $this->description('Assets registered by Meros for the admin interface.');
    }
}