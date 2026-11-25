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
}

namespace {
    function get_option(string $name, mixed $default = false): mixed {
        return $GLOBALS['options'][$name] ?? $default;
    }
    function get_current_user_id(): int {
        return $GLOBALS['userId'];
    }
}
