<?php

namespace MM\Meros\App\Admin;

use MM\Meros\Services\Contracts\Admin\MenuPageTemplate;

class SimpleSettingsPage extends MenuPageTemplate {
    public function render(): void {
        echo view('meros::admin.templates.simple-settings-page', [
            'title'    => $this->pageTitle,
            'pageSlug' => $this->pageSlug
        ]);
    }
}