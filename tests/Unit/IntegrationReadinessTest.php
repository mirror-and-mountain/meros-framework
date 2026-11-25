<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Facade;
use InvalidArgumentException;
use MM\Meros\Contracts\Register;
use MM\Meros\Registers\Data\Integrations;
use MM\Meros\Tests\Support\ReadinessOAuth;
use MM\Meros\Tests\Support\ReadinessSettings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
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
        $this->database->statement('CREATE TABLE meros_external_connections (id INTEGER PRIMARY KEY, integration_id TEXT, environment TEXT, user_id INTEGER, status TEXT, access_token TEXT)');
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
