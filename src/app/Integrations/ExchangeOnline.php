<?php

namespace MM\Meros\App\Integrations;

use MM\Meros\Services\Contracts\Integration as IntegrationDefinition;

/**
 * Framework-provided integration definition for Microsoft Exchange Online.
 */
final class ExchangeOnline extends IntegrationDefinition {
    public string $handle = 'exchange_online';

    protected string $label = 'Exchange Online';

    protected string $description = 'Connect Microsoft Exchange Online and the Microsoft Graph API for mailbox operations.';

    protected string $category = 'email';

    protected string $authType = 'oauth';

    protected string $baseUri = 'https://graph.microsoft.com';

    protected string $apiVersion = 'v1.0';

    protected array $scopes = [
        'offline_access',
        'Mail.Read',
        'Mail.Send',
        'User.Read',
    ];

    public function __construct(\MM\Meros\Services\Contracts\FeatureProvider $provider, array $props = []) {
        parent::__construct($provider, $props);

        $this->configuration(function ($fields) {
            $fields->text('client_id')->label('Client ID');
            $fields->password('client_secret')->label('Client Secret');
            $fields->text('tenant_id')->label('Tenant ID');
            $fields->text('authorize_url')->label('OAuth Authorize URL')->default('https://login.microsoftonline.com/common/oauth2/v2.0/authorize');
            $fields->text('token_url')->label('OAuth Token URL')->default('https://login.microsoftonline.com/common/oauth2/v2.0/token');
            $fields->text('redirect_uri')->label('OAuth Redirect URI')->helpText('Callback URI registered in your Azure app registration.');
            $fields->text('authority_url')->label('Authority URL')->helpText('Defaults to the Microsoft identity platform when omitted.');
            $fields->textarea('scopes')->label('Scopes')->helpText('Comma-separated OAuth scopes to request or refresh.');
        });
    }
}