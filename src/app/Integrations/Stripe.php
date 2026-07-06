<?php

namespace MM\Meros\App\Integrations;

use MM\Meros\Services\Contracts\Integration as IntegrationDefinition;

/**
 * Framework-provided integration definition for Stripe.
 */
final class Stripe extends IntegrationDefinition {
    public string $handle = 'stripe';

    protected string $label = 'Stripe';

    protected string $description = 'Enable payments and billing integrations via Stripe.';

    protected string $category = 'payments';

    protected string $authType = 'api_key';

    protected string $baseUri = 'https://api.stripe.com';

    protected string $apiVersion = 'v1';

    public function __construct(\MM\Meros\Services\Contracts\FeatureProvider $provider, array $props = []) {
        parent::__construct($provider, $props);

        $this->configuration(function ($fields) {
            $fields->select('mode')
                ->label('Mode')
                ->options([
                    'test' => 'Test',
                    'live' => 'Live',
                ])
                ->default('test');
            $fields->password('secret_key')->label('Secret Key');
            $fields->text('publishable_key')->label('Publishable Key');
            $fields->password('webhook_secret')->label('Webhook Secret');
        });
    }
}