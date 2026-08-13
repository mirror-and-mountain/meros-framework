<?php

namespace MM\Meros\App\Admin\Templates;

use MM\Meros\Services\Contracts\Admin\MenuPageTemplate;

class SimpleSettingsPage extends MenuPageTemplate {
    protected string $settingsGroup = '';
    protected string $settingsSection = '';

    /**
     * The fully-qualified view path for the template's view file.
     *
     * @var string
     */
    protected string $view = 'meros::admin.templates.simple-settings-page';

    public function settingsGroup(string $settingsGroup): static {
        $this->settingsGroup = $settingsGroup;
        return $this;
    }

    public function settingsSection(string $settingsSection): static {
        $this->settingsSection = $settingsSection;
        return $this;
    }

    public function render(): void {
        echo view($this->view, [
            'title'          => $this->pageTitle,
            'pageIntro'      => $this->pageIntro,
            'pageSlug'       => $this->pageSlug,
            'settingsGroup'  => $this->settingsGroup,
            'settingsSection' => $this->settingsSection, 
        ]);
    }
}