<?php

namespace MM\Meros\Tests\Support {
    use MM\Meros\Contracts\Features\Admin\SettingsContainer;
    use MM\Meros\Contracts\Features\Integrations\OAuthIntegration;

    class ReadinessSettings extends SettingsContainer {
        public function initialiseForTest(string $name): void {
            $this->identifier('name', 'snake');
            $this->name = $name;
        }
    }

    class SwitchTestSettings extends ReadinessSettings {
        public array $writes = [];

        public function setItemValue(string $key, mixed $value): void {
            $this->writes[$key] = $value;
        }
    }

    class ReadinessOAuth extends OAuthIntegration {
        public function initialiseForTest(string $name, SettingsContainer $settings): void {
            $this->identifier('name', 'snake');
            $this->name = $name;
            $this->settings = $settings;
            $this->merosSettings = $settings;
            $this->environments = ['sandbox' => 'Sandbox', 'production' => 'Production'];
            $this->currentEnvironmentSettingName = $name . '_current_environment';
            $this->audience = 'current_user';
        }

        protected function getAuthorizationUrl(): string {
            return 'https://authorization.example.test/authorize';
        }

        protected function getAuthorizationParams(): array {
            return [];
        }
    }
}

namespace {
    class IntegrationAjaxResponse extends \RuntimeException {
        public function __construct(public bool $success, public array $data = [], public int $status = 200) {
            parent::__construct();
        }
    }

    if (!function_exists('add_action')) {
        function add_action(string $hook, callable $callback): void {
            $GLOBALS['integrationActions'][$hook] = $callback;
        }
    }

    if (!function_exists('check_ajax_referer')) {
        function check_ajax_referer(string $action, string $queryArg, bool $stop): bool {
            return true;
        }
    }

    if (!function_exists('wp_send_json_error')) {
        function wp_send_json_error(array $data, int $status = 200): never {
            throw new IntegrationAjaxResponse(false, $data, $status);
        }
    }

    if (!function_exists('wp_send_json_success')) {
        function wp_send_json_success(): never {
            throw new IntegrationAjaxResponse(true);
        }
    }

    if (!function_exists('current_user_can')) {
        function current_user_can(string $capability): bool {
            return true;
        }
    }

    if (!function_exists('set_transient')) {
        function set_transient(string $name, mixed $value, int $expiration = 0): bool {
            $GLOBALS['transients'][$name] = $value;
            return true;
        }
    }

    if (!function_exists('get_transient')) {
        function get_transient(string $name): mixed {
            return $GLOBALS['transients'][$name] ?? false;
        }
    }

    if (!function_exists('delete_transient')) {
        function delete_transient(string $name): bool {
            unset($GLOBALS['transients'][$name]);
            return true;
        }
    }

    function get_option(string $name, mixed $default = false): mixed {
        return $GLOBALS['options'][$name] ?? $default;
    }
    function get_current_user_id(): int {
        return $GLOBALS['userId'];
    }
}
