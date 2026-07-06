<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Factory as HttpFactory;
use MM\Meros\App\Models\IntegrationAccount;
use MM\Meros\App\Models\IntegrationConnection;
use MM\Meros\App\Models\IntegrationEnvironment;
use MM\Meros\Support\Integrations\OAuthManager;
use MM\Meros\Support\Integrations\OAuthStateStore;
use PHPUnit\Framework\TestCase;

final class OAuthManagerTest extends TestCase {
    protected OAuthManager $manager;

    protected function setUp(): void {
        parent::setUp();

        IntegrationConnection::query()->delete();
        IntegrationAccount::query()->delete();
        IntegrationEnvironment::query()->delete();

        Http::swap(new HttpFactory());

        update_option('meros_framework_settings', [
            'integrations' => [
                'salesforce_client_id' => 'test-client-id',
                'salesforce_client_secret' => 'test-client-secret',
                'salesforce_authorize_url' => 'https://login.salesforce.com/services/oauth2/authorize',
                'salesforce_token_url' => 'https://login.salesforce.com/services/oauth2/token',
                'salesforce_scopes' => 'api refresh_token offline_access',
                'salesforce_redirect_uri' => 'https://example.test/wp-admin/admin-post.php?action=meros_integration_oauth_callback',
            ],
        ]);

        $this->manager = new OAuthManager(new OAuthStateStore());
    }

    public function test_callback_exchanges_code_and_persists_connection(): void {
        Http::fake(function () {
            return Http::response([
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'fresh-refresh-token',
                'instance_url' => 'https://my-org.my.salesforce.com',
                'scope' => 'api refresh_token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200);
        });

        $redirect = $this->manager->buildAuthorizationRedirect('salesforce', [
            'environment' => 'production',
            'account_label' => 'Primary Salesforce',
            'connection_label' => 'default',
            'pkce' => true,
        ]);

        $result = $this->manager->handleCallback([
            'state' => $redirect['state'],
            'code' => 'auth-code-123',
        ]);

        $this->assertTrue($result['ok']);

        $account = IntegrationAccount::query()->first();
        $this->assertNotNull($account);
        $this->assertSame('salesforce', $account->integration_handle);
        $this->assertSame('production', $account->environment);

        $connection = IntegrationConnection::query()->first();
        $this->assertNotNull($connection);
        $this->assertSame('active', $connection->status);
        $this->assertSame('fresh-access-token', $connection->access_token);
        $this->assertSame('fresh-refresh-token', $connection->refresh_token);
        $this->assertNotNull($connection->token_expires_at);
    }

    public function test_callback_rejects_invalid_state(): void {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OAuth state is invalid or has expired');

        $this->manager->handleCallback([
            'state' => 'invalid-state',
            'code' => 'auth-code-123',
        ]);
    }

    public function test_refresh_updates_access_token_and_status(): void {
        $account = IntegrationAccount::query()->create([
            'provider' => 'meros_crm',
            'integration_handle' => 'salesforce',
            'environment' => 'production',
            'label' => 'Primary Salesforce',
            'category' => 'crm',
            'auth_type' => 'oauth',
            'is_active' => true,
            'settings' => [],
        ]);

        $connection = IntegrationConnection::query()->create([
            'account_id' => $account->getKey(),
            'label' => 'default',
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token-123',
            'metadata' => [
                'token_url' => 'https://login.salesforce.com/services/oauth2/token',
                'scope' => 'api refresh_token',
            ],
            'token_expires_at' => now()->subMinutes(5),
            'is_active' => true,
            'status' => 'error',
        ]);

        Http::fake(function () {
            return Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'scope' => 'api refresh_token',
                'expires_in' => 1200,
            ], 200);
        });

        $refreshed = $this->manager->refreshConnectionToken($connection);

        $this->assertTrue($refreshed);

        $connection->refresh();

        $this->assertSame('new-access-token', $connection->access_token);
        $this->assertSame('new-refresh-token', $connection->refresh_token);
        $this->assertSame('active', $connection->status);
        $this->assertNull($connection->last_error);
        $this->assertNotNull($connection->last_refreshed_at);
    }

    public function test_refresh_failure_sets_error_details(): void {
        $account = IntegrationAccount::query()->create([
            'provider' => 'meros_crm',
            'integration_handle' => 'salesforce',
            'environment' => 'production',
            'label' => 'Primary Salesforce',
            'category' => 'crm',
            'auth_type' => 'oauth',
            'is_active' => true,
            'settings' => [],
        ]);

        $connection = IntegrationConnection::query()->create([
            'account_id' => $account->getKey(),
            'label' => 'default',
            'access_token' => 'expired-token',
            'refresh_token' => 'refresh-token-123',
            'metadata' => [
                'token_url' => 'https://login.salesforce.com/services/oauth2/token',
            ],
            'token_expires_at' => now()->subMinutes(5),
            'is_active' => true,
            'status' => 'active',
        ]);

        Http::fake(function () {
            return Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'refresh token expired',
            ], 400);
        });

        $refreshed = $this->manager->refreshConnectionToken($connection);

        $this->assertFalse($refreshed);

        $connection->refresh();

        $this->assertSame('error', $connection->status);
        $this->assertSame('token_refresh_failed', $connection->status_reason);
        $this->assertStringContainsString('refresh token expired', (string) $connection->last_error);
        $this->assertNotNull($connection->last_error_at);
    }

    public function test_callback_provider_error_is_reported(): void {
        $redirect = $this->manager->buildAuthorizationRedirect('salesforce');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('access denied by provider');

        $this->manager->handleCallback([
            'state' => $redirect['state'],
            'error' => 'access_denied',
            'error_description' => 'access denied by provider',
        ]);
    }

    public function test_build_authorization_redirect_uses_sandbox_salesforce_endpoint(): void {
        $redirect = $this->manager->buildAuthorizationRedirect('salesforce', [
            'environment' => 'sandbox',
        ]);

        $this->assertStringStartsWith(
            'https://test.salesforce.com/services/oauth2/authorize',
            $redirect['url']
        );

        $this->assertSame('sandbox', $redirect['environment']);
    }

    public function test_disconnect_clears_tokens_and_marks_status(): void {
        $account = IntegrationAccount::query()->create([
            'provider' => 'meros_crm',
            'integration_handle' => 'salesforce',
            'environment' => 'production',
            'label' => 'Primary Salesforce',
            'category' => 'crm',
            'auth_type' => 'oauth',
            'is_active' => true,
            'settings' => [],
        ]);

        $connection = IntegrationConnection::query()->create([
            'account_id' => $account->getKey(),
            'label' => 'default',
            'access_token' => 'active-token',
            'refresh_token' => 'refresh-token-123',
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->manager->disconnectConnection($connection);

        $connection->refresh();
        $account->refresh();

        $this->assertNull($connection->access_token);
        $this->assertNull($connection->refresh_token);
        $this->assertFalse((bool) $connection->is_active);
        $this->assertSame('disconnected', $connection->status);
        $this->assertNotNull($connection->revoked_at);
        $this->assertFalse((bool) $account->is_active);
    }

    public function test_connection_is_usable_for_active_unexpired_connection(): void {
        $account = IntegrationAccount::query()->create([
            'provider' => 'meros_crm',
            'integration_handle' => 'salesforce',
            'environment' => 'production',
            'label' => 'Primary Salesforce',
            'category' => 'crm',
            'auth_type' => 'oauth',
            'is_active' => true,
            'settings' => [],
        ]);

        $connection = IntegrationConnection::query()->create([
            'account_id' => $account->getKey(),
            'label' => 'default',
            'access_token' => 'active-token',
            'refresh_token' => 'refresh-token-123',
            'token_expires_at' => now()->addMinutes(20),
            'is_active' => true,
            'status' => 'active',
        ]);

        $this->assertTrue($this->manager->connectionIsUsable($connection));

        $connection->status = 'error';
        $connection->save();

        $this->assertFalse($this->manager->connectionIsUsable($connection));
    }
}
