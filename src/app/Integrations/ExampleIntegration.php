<?php 

namespace MM\Meros\App\Integrations;

use MM\Meros\Services\Contracts\Integrations\OAuthIntegration;

class ExampleIntegration extends OAuthIntegration {
    public string $handle = 'example_integration';

    protected string $label = 'Example Integration';

    protected string $description = 'An example integration for demonstration purposes.';

    protected string $category = 'general';

    protected string $baseUri = 'https://api.example.com';

    protected string $apiVersion = 'v1';
}