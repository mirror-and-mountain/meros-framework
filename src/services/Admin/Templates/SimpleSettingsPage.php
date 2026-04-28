<?php

namespace MM\Meros\Services\Admin\Templates;

use MM\Meros\Services\Contracts\MenuPageTemplate;

class SimpleSettingsPage extends MenuPageTemplate {
    public function render(): void {
        echo view('meros::admin.templates.simple-settings-page', [
            'title'    => $this->title,
            'pageSlug' => $this->pageSlug
        ]);
    }
}