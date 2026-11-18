<?php

namespace MM\Meros\App\Assets;

use MM\Meros\Contracts\Features\Assets\AssetGroup;

final class MFormsDeps extends AssetGroup {
    protected function configure(): void {
        $assets = [
            'mforms-nice-forms-style'  => 'mforms/nice-forms/style-index.css',
            'mforms-tom-select-style'  => 'mforms/tomselect/style-index.css',
            'mforms-tom-select-script' => 'mforms/tomselect/index.js',
            'mforms-quill-style'       => 'mforms/quill/style-index.css',
            'mforms-quill-script'      => 'mforms/quill/index.js'
        ];

        foreach ($assets as $handle => $path) {
            $this->add($path, $handle, ['site', 'admin', 'editor']);
        }

        $this->name('meros_forms_dependencies');
        $this->description('Dependencies for MForms.');
        $this->register();
    }
}