<?php

require_once __DIR__ . '/../../../autoload.php';

use Illuminate\Container\Container;
use Illuminate\Config\Repository;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Psr\Log\AbstractLogger;

if (!function_exists('now')) {
    function now() {
        return \Illuminate\Support\Carbon::now();
    }
}

$GLOBALS['__meros_test_options'] = [];
$GLOBALS['__meros_test_transients'] = [];

if (!function_exists('get_option')) {
    function get_option(string $key, $default = false) {
        return $GLOBALS['__meros_test_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $key, $value): bool {
        $GLOBALS['__meros_test_options'][$key] = $value;

        return true;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $ttl): bool {
        $GLOBALS['__meros_test_transients'][$key] = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];

        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient(string $key) {
        $entry = $GLOBALS['__meros_test_transients'][$key] ?? null;

        if (!is_array($entry)) {
            return false;
        }

        if (($entry['expires_at'] ?? 0) < time()) {
            unset($GLOBALS['__meros_test_transients'][$key]);

            return false;
        }

        return $entry['value'] ?? false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient(string $key): bool {
        unset($GLOBALS['__meros_test_transients'][$key]);

        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(array $args, string $url): string {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($args);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
    }
}

$app = new Container();
Container::setInstance($app);
Facade::setFacadeApplication($app);

$appKey = 'base64:' . base64_encode(random_bytes(32));
$keyBytes = base64_decode(substr($appKey, 7));

$app->instance('config', new Repository([
    'app' => [
        'key' => $appKey,
        'cipher' => 'AES-256-CBC',
    ],
]));

$app->instance('encrypter', new Encrypter($keyBytes, 'AES-256-CBC'));
$app->instance('http', new HttpFactory());
$app->instance('log', new class extends AbstractLogger {
    public function log($level, $message, array $context = []): void {
    }
});

$capsule = new Capsule($app);
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

$app->instance('db', $capsule->getDatabaseManager());

$schema = $capsule->schema();

$schema->create('meros_integration_accounts', function ($table) {
    $table->increments('id');
    $table->string('provider');
    $table->string('integration_handle');
    $table->string('environment')->default('production');
    $table->string('label');
    $table->string('category')->default('general');
    $table->string('auth_type')->default('oauth');
    $table->boolean('is_active')->default(true);
    $table->text('settings')->nullable();
    $table->timestamps();
});

$schema->create('meros_integration_environments', function ($table) {
    $table->increments('id');
    $table->string('provider');
    $table->string('integration_handle');
    $table->string('environment');
    $table->string('label');
    $table->boolean('is_default')->default(false);
    $table->text('settings')->nullable();
    $table->timestamps();
});

$schema->create('meros_integration_connections', function ($table) {
    $table->increments('id');
    $table->unsignedInteger('account_id');
    $table->string('label');
    $table->text('api_key')->nullable();
    $table->text('access_token')->nullable();
    $table->text('refresh_token')->nullable();
    $table->text('id_token')->nullable();
    $table->text('scopes')->nullable();
    $table->text('metadata')->nullable();
    $table->timestamp('token_expires_at')->nullable();
    $table->timestamp('last_used_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->string('status')->default('inactive');
    $table->string('status_reason')->nullable();
    $table->text('last_error')->nullable();
    $table->timestamp('last_error_at')->nullable();
    $table->timestamp('connected_at')->nullable();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamp('last_refreshed_at')->nullable();
    $table->timestamps();
});
