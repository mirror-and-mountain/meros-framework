<?php

namespace MM\Meros\Scripts;

use InvalidArgumentException;
use MM\Meros\Contracts\Features\Integrations\OAuthIntegration;
use MM\Meros\Facades\Integrations;

class IntegrationCommands {
    /**
     * Print the OAuth authorization URL for a config-managed integration.
     *
     * ## OPTIONS
     *
     * <integration>
     * : The registered integration identifier.
     *
     * [<environment>]
     * : The environment to connect. Defaults to the active environment.
     *
     * @subcommand connect
     */
    public function connect(array $args, array $assoc): void {
        [$integration, $environment] = $this->resolveIntegration($args);

        if (get_current_user_id() <= 0 || !current_user_can('manage_options')) {
            $this->fail('Run this command as a WordPress administrator using --user=<user> so the OAuth callback uses the same account.');
        }

        $url = $integration->startAuthFlowForEnvironment($environment);
        if ($url === '') {
            $this->fail('Unable to start OAuth authorization. Confirm the integration is enabled and its required settings are present.');
        }

        \WP_CLI::line('Open this URL in a browser signed in as the same WordPress administrator:');
        \WP_CLI::line($url);
    }

    /**
     * Mark an integration connection as revoked.
     *
     * ## OPTIONS
     *
     * <integration>
     * : The registered integration identifier.
     *
     * [<environment>]
     * : The environment to revoke. Defaults to the active environment.
     *
     * @subcommand revoke-token
     */
    public function revokeToken(array $args, array $assoc): void {
        [$integration, $environment] = $this->resolveIntegration($args);
        $count = $integration->revokeToken($environment);

        if ($count === 0) {
            \WP_CLI::warning("No connections found for '{$integration->getName()}' in '{$environment}'.");
            return;
        }

        \WP_CLI::success("Revoked {$count} connection(s) for '{$integration->getName()}' in '{$environment}'.");
    }

    /**
     * Delete an integration connection.
     *
     * ## OPTIONS
     *
     * <integration>
     * : The registered integration identifier.
     *
     * [<environment>]
     * : The environment to delete. Defaults to the active environment.
     *
     * @subcommand delete-token
     */
    public function deleteToken(array $args, array $assoc): void {
        [$integration, $environment] = $this->resolveIntegration($args);
        $count = $integration->deleteToken($environment);

        if ($count === 0) {
            \WP_CLI::warning("No connections found for '{$integration->getName()}' in '{$environment}'.");
            return;
        }

        \WP_CLI::success("Deleted {$count} connection(s) for '{$integration->getName()}' in '{$environment}'.");
    }

    /**
     * @return array{OAuthIntegration, string}
     */
    private function resolveIntegration(array $args): array {
        if (count($args) < 1 || count($args) > 2) {
            $this->fail('Specify an integration identifier and, optionally, an environment.');
        }

        try {
            $integration = Integrations::connect($args[0]);
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
        }

        if (!$integration instanceof OAuthIntegration) {
            $this->fail("Integration '{$args[0]}' does not support OAuth connections.");
        }

        $environment = $args[1] ?? $integration->getCurrentEnvironment();
        try {
            if (!$integration->isConfigurationLocked($environment)) {
                $this->fail("Integration '{$integration->getName()}' is not configured with allow_configuration set to false for '{$environment}'.");
            }
        } catch (InvalidArgumentException $exception) {
            $this->fail($exception->getMessage());
        }

        return [$integration, $environment];
    }

    private function fail(string $message): never {
        \WP_CLI::error($message);
        throw new \LogicException('WP-CLI returned after reporting a fatal command error.');
    }
}
