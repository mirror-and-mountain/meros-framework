<?php

namespace MM\Meros\Contracts\Features\Integrations\Concerns;

use MM\Meros\Contracts\Features\Admin\Setting;

trait UsesClientId {
    use Abstracts;

    protected string $clientIdLabel = 'Client ID';
    protected string $clientIdDescription = "The application's client id. Sometimes referred to as an 'application id.'";
    protected string $clientIdPlaceholder = '';
    protected string $clientIdDefault = '';

    final protected function configureClientId(): void {
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('client_id');
            $setting->label($this->clientIdLabel);
            $setting->description($this->clientIdDescription);
            $setting->encrypt();
            $setting->field('text', [
                'placeholder' => $this->clientIdPlaceholder,
                'default'     => $this->clientIdDefault 
            ]);
        });
    }
}