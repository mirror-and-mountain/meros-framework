<?php

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Contracts\Features\Security\Capability;
use MM\Meros\Contracts\Features\Security\Role;
use MM\Meros\Contracts\Providers\FeatureProvider;
use MM\Meros\App\Security\Orchestrator as FormSecurityOrchestrator;
use MM\Meros\App\Security\Capabilities\EditFormResponses;
use MM\Meros\App\Security\Capabilities\EditForms;
use MM\Meros\App\Security\Capabilities\ReadFormResponses;
use MM\Meros\App\Security\Capabilities\ReadForms;
use MM\Meros\App\Security\Roles\FormManager;
use MM\Meros\App\Security\Roles\FormReader;
use MM\Meros\App\Admin\Settings\Containers\MerosRoleSettings;
use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Registers\Admin\Settings as SettingsRegister;
use MM\Meros\Registers\Security\Capabilities as CapabilitiesRegister;
use MM\Meros\Registers\Security\Roles as RolesRegister;
use MM\Meros\Support\Registers as RegistersCollection;
use MM\Meros\App\Admin\Pages\MerosSecurity;

function check(bool $condition, string $message): void {
    \PHPUnit\Framework\Assert::assertTrue($condition, $message);
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void {}

function get_option(string $name, mixed $default = false): mixed {
    return $GLOBALS['options'][$name] ?? $default;
}

function get_role(string $name): ?TestWordPressRole {
    return $GLOBALS['roles'][$name] ?? null;
}

function add_role(string $name, string $displayName, array $capabilities = []): ?TestWordPressRole {
    if (isset($GLOBALS['roles'][$name])) {
        return null;
    }

    $role = new TestWordPressRole($capabilities);
    $GLOBALS['roles'][$name] = $role;
    return $role;
}

function wp_kses_post(string $content): string {
    return $content;
}

function sanitize_text_field(string $value): string {
    return trim(strip_tags($value));
}

function register_setting(string $group, string $name, array $args): void {
    $GLOBALS['registeredSettings'][$name] = $args;
}

function get_theme_file_path(): string {
    return dirname(dirname(__DIR__));
}

function get_template_directory_uri(): string {
    return 'https://example.test/theme';
}

class TestWordPressRole {
    public function __construct(public array $capabilities) {}

    public function add_cap(string $capability): void {
        $this->capabilities[$capability] = true;
    }

    public function remove_cap(string $capability): void {
        unset($this->capabilities[$capability]);
    }

    public function has_cap(string $capability): bool {
        return $this->capabilities[$capability] ?? false;
    }
}

class RoleConfigurationProvider implements FeatureProvider {
    public function get(): static { return $this; }
    public function getHandle(bool $slug = false): string { return 'role_configuration_tests'; }
    public function getName(): string { return 'Role Configuration Tests'; }
    public function getPath(string $subPath = ''): string { return $subPath; }
    public function getUri(string $subPath = ''): string { return $subPath; }
    public function getPreference(string $key, bool $fullPath = true): mixed { return null; }
    public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        throw new LogicException('Not used in this test.');
    }
}

class ConfigurableManageReports extends Capability {
    protected function configure(): void {
        $this->name('manage_reports');
        $this->roles(['editor']);
        $this->label('Manage reports');
        $this->userConfigurable();
    }
}

class FixedManageArchive extends Capability {
    protected function configure(): void {
        $this->name('manage_archive');
    }
}

class ConfigurableReportsEditor extends Role {
    protected function configure(): void {
        $this->handle('reports_editor');
        $this->label('Reports Editor');
        $this->capabilities([
            'read' => true,
            'manage_reports' => true,
            'manage_archive' => true,
        ]);
        $this->userConfigurable();
    }
}

class InvalidAdministratorRole extends Role {
    protected function configure(): void {
        $this->handle('administrator');
    }
}


function wp_roles(): object {
    $roles = [];
    foreach ($GLOBALS['roles'] as $handle => $role) {
        $roles[$handle] = ['name' => ucwords(str_replace('_', ' ', $handle)), 'capabilities' => $role->capabilities];
    }
    return (object) ['roles' => $roles];
}

function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
function esc_attr(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
function esc_url(string $value): string { return htmlspecialchars($value, ENT_QUOTES); }
function admin_url(string $path): string { return 'https://example.test/wp-admin/' . $path; }
function checked(bool $value): void { if ($value) { echo 'checked="checked"'; } }
function disabled(bool $value): void { if ($value) { echo 'disabled="disabled"'; } }
function wp_nonce_field(string $action): void {}
function submit_button(string $label): void { echo '<button>' . esc_html($label) . '</button>'; }
function wp_unslash(array $value): array { return $value; }
function current_user_can(string $capability): bool { return $GLOBALS['canManage']; }
function check_admin_referer(string $action): void {
    if (!$GLOBALS['validNonce']) { wp_die('Invalid nonce'); }
}
function wp_die(string $message): never { throw new RuntimeException($message); }
function update_option(string $name, mixed $value): bool {
    if ($GLOBALS['failOptionUpdates'] ?? false) {
        return false;
    }
    if (isset($GLOBALS['registeredSettings'][$name]['sanitize_callback'])) {
        $value = ($GLOBALS['registeredSettings'][$name]['sanitize_callback'])($value);
    }
    $GLOBALS['options'][$name] = $value;
    return true;
}
function add_query_arg(array $args, string $url): string { return $url . '?' . http_build_query($args); }
class TestRoleSettingsRedirect extends RuntimeException {}
function wp_safe_redirect(string $url): void { throw new TestRoleSettingsRedirect($url); }
