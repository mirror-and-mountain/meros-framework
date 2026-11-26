<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Integrations\Integration as IntegrationContract;
use MM\Meros\Contracts\Features\Integrations\OAuthIntegration;
use MM\Meros\Registers\Data\Integrations;
use MM\Meros\Tests\Support\ReadinessOAuth;
use MM\Meros\Tests\Support\ReadinessSettings;
use MM\Meros\Tests\Support\SwitchTestSettings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Tests integration readiness refreshes and resolution through identifiers, classes and facades.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IntegrationReadinessTest extends TestCase {
    private Connection $database;
    private ReadinessSettings $settings;
    private ReadinessOAuth $integration;

    protected function setUp(): void {
        require_once dirname(__DIR__) . '/Support/IntegrationReadiness.php';
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->database = $capsule->getConnection();
        $this->database->statement('CREATE TABLE meros_external_connections (id INTEGER PRIMARY KEY, integration_id TEXT, environment TEXT, user_id INTEGER, status TEXT, access_token TEXT, is_active INTEGER, status_reason TEXT, revoked_at DATETIME, created_at DATETIME, updated_at DATETIME)');
        $encrypter = new Encrypter(str_repeat('a', 32), 'AES-256-CBC');
        Model::encryptUsing($encrypter);
        $this->settings = (new ReflectionClass(ReadinessSettings::class))->newInstanceWithoutConstructor();
        $this->settings->initialiseForTest('readiness_options');
        $this->integration = (new ReflectionClass(ReadinessOAuth::class))->newInstanceWithoutConstructor();
        $this->integration->initialiseForTest('provider_readiness', $this->settings);
        $GLOBALS['options']['readiness_options'] = [
            'provider_readiness_enabled' => true,
            'provider_readiness_current_environment' => 'sandbox',
        ];
        $GLOBALS['userId'] = 1;
        $this->database->table('meros_external_connections')->insert([
            ['integration_id' => 'provider_readiness', 'environment' => 'sandbox', 'user_id' => 1, 'status' => 'connected', 'access_token' => $encrypter->encryptString('sandbox-token')],
            ['integration_id' => 'provider_readiness', 'environment' => 'production', 'user_id' => 2, 'status' => 'connected', 'access_token' => $encrypter->encryptString('production-token')],
        ]);
    }

    public function testReadinessRefreshesSettingsUserEnvironmentAndConnection(): void {
        self::assertTrue($this->integration->ready());
        $GLOBALS['options']['readiness_options']['provider_readiness_enabled'] = false;
        self::assertFalse($this->integration->ready());
        $GLOBALS['options']['readiness_options']['provider_readiness_enabled'] = true;
        $GLOBALS['userId'] = 2;
        self::assertFalse($this->integration->ready());
        $GLOBALS['options']['readiness_options']['provider_readiness_current_environment'] = 'production';
        self::assertTrue($this->integration->ready());
        $this->database->table('meros_external_connections')->where('environment', 'production')->update(['status' => 'revoked']);
        self::assertFalse($this->integration->ready());
        $this->database->table('meros_external_connections')->where('environment', 'production')->update(['status' => 'connected', 'access_token' => null]);
        self::assertFalse($this->integration->ready());
    }

    public function testTokenOperationsUseTheRequestedEnvironmentAndCurrentUser(): void {
        $this->database->table('meros_external_connections')->insert([
            'integration_id' => 'provider_readiness',
            'environment' => 'production',
            'user_id' => 1,
            'status' => 'connected',
            'access_token' => null,
        ]);

        self::assertSame(1, $this->integration->revokeToken('production'));
        $revoked = $this->database->table('meros_external_connections')
            ->where('environment', 'production')
            ->where('user_id', 1)
            ->first();
        self::assertSame('revoked', $revoked->status);
        self::assertSame(0, $revoked->is_active);

        self::assertSame(1, $this->integration->deleteToken('production'));
        self::assertSame(1, $this->database->table('meros_external_connections')
            ->where('environment', 'production')
            ->where('user_id', 2)
            ->count());
        self::assertSame('sandbox', $this->integration->getCurrentEnvironment());
    }

    public function testOAuthEnvironmentOverrideIsPreservedThroughTheCallbackState(): void {
        $url = $this->integration->startAuthFlowForEnvironment('production');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        self::assertStringStartsWith('meros_env_', $params['state']);
        self::assertSame('sandbox', $this->integration->getCurrentEnvironment());

        $environment = (new ReflectionMethod(OAuthIntegration::class, 'resolveAuthorizationEnvironment'))
            ->invoke($this->integration, ['state' => $params['state']]);
        self::assertSame('production', $environment);

        (new ReflectionMethod(IntegrationContract::class, 'overrideEnvironment'))
            ->invoke($this->integration, $environment);
        self::assertSame('production', $this->integration->getCurrentEnvironment());

        (new ReflectionMethod(IntegrationContract::class, 'clearEnvironmentOverride'))
            ->invoke($this->integration);
        self::assertSame('sandbox', $this->integration->getCurrentEnvironment());
    }

    public function testEnvironmentSwitchAjaxRespectsConfiguration(): void {
        $container = new Container();
        $container->instance('config', new Repository());
        Container::setInstance($container);
        $settings = (new ReflectionClass(SwitchTestSettings::class))->newInstanceWithoutConstructor();
        $settings->initialiseForTest('readiness_options');
        $integration = (new ReflectionClass(ReadinessOAuth::class))->newInstanceWithoutConstructor();
        $integration->initialiseForTest('provider_readiness', $settings);
        (new ReflectionMethod(IntegrationContract::class, 'initEnvironmentSwitch'))->invoke($integration);
        $switch = $GLOBALS['integrationActions']['wp_ajax_meros_switch_integration_environment_provider_readiness'];
        $_POST['env'] = 'production';

        config(['integrations.provider_readiness.allow_environment_switching' => false]);
        try {
            $switch();
            self::fail('A disabled switch must reject the request.');
        } catch (\IntegrationAjaxResponse $response) {
            self::assertFalse($response->success);
            self::assertSame(403, $response->status);
            self::assertSame([], $settings->writes);
        }

        config(['integrations.provider_readiness.allow_environment_switching' => true]);
        try {
            $switch();
            self::fail('The response must be sent.');
        } catch (\IntegrationAjaxResponse $response) {
            self::assertTrue($response->success);
            self::assertSame(['provider_readiness_current_environment' => 'production'], $settings->writes);
        }
    }

    public function testRegistrationResolvesIdentifiersClassesAndFacades(): void {
        $register = (new ReflectionClass(Integrations::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Register::class, 'features'))->setValue($register, collect([$this->integration]));
        self::assertSame($this->integration, $register->connect('provider_readiness'));
        self::assertSame($this->integration, $register->connect('readiness_o_auth'));
        self::assertSame($this->integration, $register->connect(ReadinessOAuth::class));
        $container = new Container();
        $container->instance('meros.registers.integrations', $register);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();
        self::assertSame($this->integration, \MM\Meros\Facades\Integrations::connect('provider_readiness'));
        self::assertSame($this->integration, \MM\Meros\Facades\Data\Integrations::connect('provider_readiness'));
        try {
            $register->connect('missing');
            self::fail('An unregistered identifier must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('not registered', $exception->getMessage());
        }
        $duplicate = (new ReflectionClass(ReadinessOAuth::class))->newInstanceWithoutConstructor();
        $duplicate->initialiseForTest('other_readiness', $this->settings);
        (new ReflectionProperty(Register::class, 'features'))->setValue($register, collect([$this->integration, $duplicate]));
        try {
            $register->connect('readiness_o_auth');
            self::fail('An ambiguous short name must fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('ambiguous', $exception->getMessage());
        }
        self::assertSame($duplicate, $register->connect('other_readiness'));
    }
}
