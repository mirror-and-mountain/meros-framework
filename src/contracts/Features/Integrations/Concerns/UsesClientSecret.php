<?php

namespace MM\Meros\Contracts\Features\Integrations\Concerns;

use MM\Meros\Contracts\Features\Admin\Setting;

trait UsesClientSecret {
    use Abstracts;

    protected string $clientSecretLabel = 'Client Secret';
    protected string $clientSecretDescription = "The application's client secret. Sometimes referred to as an 'application secret'.";
    protected string $clientSecretPlaceholder = '';
    protected string $clientSecretDefault = '';

    final protected function configureClientSecret(): void {
        $this->settings()->add('string', function (Setting $setting) {
            $setting->name('client_secret');
            $setting->label($this->clientSecretLabel);
            $setting->description($this->clientSecretDescription);
            $setting->encrypt();
            $setting->field('password', [
                'placeholder' => $this->clientSecretPlaceholder,
                'default'     => $this->clientSecretDefault
            ]);
        });
    }
}