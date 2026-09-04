<?php

namespace MM\Meros\Contracts\Features\Integrations\Concerns;

use MM\Meros\Contracts\Features\Admin\Setting;

trait UsesBaseUrl {
    use Abstracts;

    protected string $baseUrlLabel = 'Base URL';
    protected string $baseUrlDescription = 'The base url of the application.';
    protected string $baseUrlPlaceholder = 'e.g. https://my-app.com';
    protected string $baseUrlDefault = '';

    final protected function configureBaseUrl(): void {
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('base_url');
            $setting->label($this->baseUrlLabel);
            $setting->description($this->baseUrlDescription);
            $setting->field('url', [
                'placeholder' => $this->baseUrlPlaceholder,
                'default'     => $this->baseUrlDefault
            ]);
        });
    }
}