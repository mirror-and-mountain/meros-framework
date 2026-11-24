<?php

require __DIR__ . '/../../../autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Features\Integrations\Integration;
use MM\Meros\Contracts\Features\Integrations\OAuthIntegration;
use MM\Meros\Contracts\Register;
use MM\Meros\Registers\Data\Integrations;

function get_option(string $name, mixed $default = false): mixed {
    return $GLOBALS['options'][$name] ?? $default;
}

function get_current_user_id(): int {
    return $GLOBALS['userId'];
}

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectInvalid(callable $callback, string $message): void {
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        check(str_contains($exception->getMessage(), $message), 'Unexpected error: ' . $exception->getMessage());
        return;
    }
    throw new RuntimeException('Expected an invalid argument: ' . $message);
}

class ReadinessSettings extends SettingsContainer {
    public function initialiseForTest(string $name): void {
        $this->identifier('name', 'snake');
        $this->name = $name;
    }
}

class ReadinessOAuth extends OAuthIntegration {
    public function initialiseForTest(string $name, SettingsContainer $settings): void {
        $this->identifier('name', 'snake');
        $this->name = $name;
        $this->settings = $settings;
        $this->merosSettings = $settings;
        $this->currentEnvironmentSettingName = $name . '_current_environment';
        $this->audience = 'current_user';
    }
}

$capsule = new Capsule();
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$capsule->setAsGlobal();
$capsule->bootEloquent();
$database = $capsule->getConnection();
$database->statement('CREATE TABLE meros_external_connections (id INTEGER PRIMARY KEY, integration_id TEXT, environment TEXT, user_id INTEGER, status TEXT, access_token TEXT)');
$encrypter = new Encrypter(str_repeat('a', 32), 'AES-256-CBC');
Model::encryptUsing($encrypter);

$settings = (new ReflectionClass(ReadinessSettings::class))->newInstanceWithoutConstructor();
$settings->initialiseForTest('readiness_options');
$integration = (new ReflectionClass(ReadinessOAuth::class))->newInstanceWithoutConstructor();
$integration->initialiseForTest('provider_readiness', $settings);
$GLOBALS['options']['readiness_options'] = [
    'provider_readiness_enabled' => true,
    'provider_readiness_current_environment' => 'sandbox',
];
$GLOBALS['userId'] = 1;
$database->table('meros_external_connections')->insert([
    ['integration_id' => 'provider_readiness', 'environment' => 'sandbox', 'user_id' => 1, 'status' => 'connected', 'access_token' => $encrypter->encryptString('sandbox-token')],
    ['integration_id' => 'provider_readiness', 'environment' => 'production', 'user_id' => 2, 'status' => 'connected', 'access_token' => $encrypter->encryptString('production-token')],
]);

check($integration->ready(), 'Enabled OAuth integration with a current-user connection must be ready.');
$GLOBALS['options']['readiness_options']['provider_readiness_enabled'] = false;
check(!$integration->ready(), 'Readiness must refresh the enabled setting.');
$GLOBALS['options']['readiness_options']['provider_readiness_enabled'] = true;
$GLOBALS['userId'] = 2;
check(!$integration->ready(), 'A connection belonging to another user must not be used.');
$GLOBALS['options']['readiness_options']['provider_readiness_current_environment'] = 'production';
check($integration->ready(), 'Readiness must use the newly selected environment and user.');
$database->table('meros_external_connections')->where('environment', 'production')->update(['status' => 'revoked']);
check(!$integration->ready(), 'Readiness must detect a connection revoked after resolution.');
$database->table('meros_external_connections')->where('environment', 'production')->update(['status' => 'connected', 'access_token' => null]);
check(!$integration->ready(), 'A connected record without an access token must not be ready.');

$register = (new ReflectionClass(Integrations::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(Register::class, 'features'))->setValue($register, collect([$integration]));
check($register->connect('provider_readiness') === $integration, 'Resolve by registered identifier even when unavailable.');
check($register->connect('readiness_o_auth') === $integration, 'Resolve by unique short class name.');
check($register->connect(ReadinessOAuth::class) === $integration, 'Resolve by class name.');
$container = new Container();
$container->instance('meros.registers.integrations', $register);
Facade::setFacadeApplication($container);
Facade::clearResolvedInstances();
check(\MM\Meros\Facades\Integrations::connect('provider_readiness') === $integration, 'The public facade must resolve the integration.');
check(\MM\Meros\Facades\Data\Integrations::connect('provider_readiness') === $integration, 'The data facade must resolve the same integration.');
expectInvalid(fn () => $register->connect('missing'), 'not registered');
$duplicate = (new ReflectionClass(ReadinessOAuth::class))->newInstanceWithoutConstructor();
$duplicate->initialiseForTest('other_readiness', $settings);
(new ReflectionProperty(Register::class, 'features'))->setValue($register, collect([$integration, $duplicate]));
expectInvalid(fn () => $register->connect('readiness_o_auth'), 'ambiguous');
check($register->connect('other_readiness') === $duplicate, 'A full identifier must disambiguate short names.');

echo "Integration readiness tests passed.\n";
