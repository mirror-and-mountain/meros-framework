<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class Site extends AssetGroup {
    protected function configure(): void {
        $assets = [
            [
                'path' => 'site/index.js',
                'dependencies' => ['group_meros_forms_assets'],
            ],
            [
                'path' => 'site/style-index.css',
                'dependencies' => ['group_meros_forms_assets'],
            ]
        ];

        $this->add($assets, ['site']);
        $this->name('meros_site_assets');
        $this->description('Assets registered by Meros for the frontend of the site.');
    }
}