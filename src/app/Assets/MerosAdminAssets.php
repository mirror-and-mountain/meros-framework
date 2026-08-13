<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class MerosAdminAssets extends AssetGroup {
    protected function configure(): void {
        $this->assets([
            'admin' => [
                'meros-admin-pages/admin/index.js',
                'meros-admin-pages/admin/style-index.css',
            ]
        ]);

        $this->name('meros_admin_assets');
        $this->description('Assets registered by Meros for the admin interface.');
    }
}