<?php

namespace MM\Meros\App\Admin\Templates;

use MM\Meros\Services\Contracts\Admin\MenuPageTemplate;

class SimpleSettingsPage extends MenuPageTemplate {
    /**
     * The fully-qualified view path for the template's view file.
     *
     * @var string
     */
    protected string $view = 'meros::admin.templates.simple-settings-page';

    public function render(): void {
        echo view($this->view, [
            'title'    => $this->pageTitle,
            'pageSlug' => $this->pageSlug
        ]);
    }
}