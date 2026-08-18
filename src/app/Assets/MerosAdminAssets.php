<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class MerosAdminAssets extends AssetGroup {
    protected function configure(): void {
        $this->assets([
            'admin' => [
                'admin/index.js',
                'admin/style-index.css',
            ]
        ]);

        $this->name('meros_admin_assets');
        $this->description('Assets registered by Meros for the admin interface.');
    }
}