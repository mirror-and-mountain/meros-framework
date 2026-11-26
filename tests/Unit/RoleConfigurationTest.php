<?php

namespace MM\Meros\Tests\Unit;

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
use ConfigurableManageReports;
use ConfigurableReportsEditor;
use DOMDocument;
use DOMXPath;
use FixedManageArchive;
use InvalidAdministratorRole;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use RoleConfigurationProvider;
use RuntimeException;
use TestRoleSettingsRedirect;
use TestWordPressRole;

use PHPUnit\Framework\TestCase;

/**
 * Tests role and capability configuration, including permissions matrix validation and persistence.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RoleConfigurationTest extends TestCase {
    protected function setUp(): void {
        require_once dirname(__DIR__) . '/Support/RoleConfigurationTestFixtures.php';
    }

    public function test_form_capabilities_preserve_editor_and_administrator_access(): void {
        $GLOBALS['roles'] = [
            'administrator' => new TestWordPressRole(['read' => true]),
            'editor' => new TestWordPressRole(['read' => true, 'edit_posts' => true]),
        ];
        $provider = new RoleConfigurationProvider();
        ReadForms::__make_from_registered($provider)->register();
        EditForms::__make_from_registered($provider)->register();

        foreach (['administrator', 'editor'] as $role) {
            $this->assertTrue(get_role($role)->has_cap('read_forms'));
            $this->assertTrue(get_role($role)->has_cap('edit_forms'));
        }
    }

    public function test_default_role_assignments_require_optin_and_saved_revocations_survive_defaults(): void {
        $provider = new RoleConfigurationProvider();
        $container = new Container();
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();
        $container->instance('meros.framework', $provider);
        $container->instance('meros.helpers.registers', new RegistersCollection());
        $capabilities = new CapabilitiesRegister();
        $containers = new SettingsContainers();
        $container->instance('meros.registers.capabilities', $capabilities);
        $container->instance('meros.registers.settings', new SettingsRegister());
        $container->instance('meros.registers.settings_containers', $containers);
        $containers->checkout($provider)->register(MerosRoleSettings::class);
        $readForms = $capabilities->checkout($provider)->makeFrom(ReadForms::class);
        $capabilities->checkout($provider)->makeFrom(EditForms::class);
        $capabilities->checkout($provider)->register(ConfigurableManageReports::class, 'reports');
        $reports = $capabilities->checkout($provider)->makeFrom('reports');
        foreach (Role::BUILT_IN_ROLES as $role) {
            $GLOBALS['roles'][$role] = new TestWordPressRole(['read' => true]);
        }
        foreach ($capabilities->all() as $capability) {
            $capability->register();
        }
        $GLOBALS['options'][Role::OPTION_NAME] = [
            'role_capabilities' => [
                'subscriber' => ['read_forms' => true, 'edit_forms' => false, 'manage_reports' => true],
                'editor' => ['read_forms' => false],
                'administrator' => ['read_forms' => false],
            ],
        ];

        Role::applyBuiltInCapabilitySettings();
        $this->assertTrue(get_role('subscriber')->has_cap('read_forms'));
        $this->assertFalse(get_role('subscriber')->has_cap('edit_forms'));
        $this->assertFalse(get_role('subscriber')->has_cap('manage_reports'));
        $this->assertFalse(get_role('editor')->has_cap('read_forms'));
        $this->assertTrue(get_role('administrator')->has_cap('read_forms'));

        $readForms->register();
        Role::applyBuiltInCapabilitySettings();
        $this->assertFalse(get_role('editor')->has_cap('read_forms'));
        $GLOBALS['options'][Role::OPTION_NAME]['role_capabilities']['subscriber']['read_forms'] = false;
        Role::applyBuiltInCapabilitySettings();
        $this->assertFalse(get_role('subscriber')->has_cap('read_forms'));

        $reports->assignableToDefaultRoles()->userConfigurable(false);
        $this->assertNotContains('manage_reports', Role::getConfigurableCapabilities('subscriber'));
        $reports->userConfigurable();
        $this->assertContains('manage_reports', Role::getConfigurableCapabilities('subscriber'));
        $reports->assignableToDefaultRoles(false);
        $this->assertNotContains('manage_reports', Role::getConfigurableCapabilities('subscriber'));
    }

    public function testRolesAndCapabilityConfiguration(): void {
        check(class_exists(RolesRegister::class), 'The role register must be available to providers.');
        check(class_exists(CapabilitiesRegister::class), 'The capability register must be available to providers.');
        
        $provider = new RoleConfigurationProvider();
        $formManager = FormManager::__make_from_registered($provider);
        $formReader = FormReader::__make_from_registered($provider);
        $readForms = ReadForms::__make_from_registered($provider);
        $editForms = EditForms::__make_from_registered($provider);
        $readFormResponses = ReadFormResponses::__make_from_registered($provider);
        $editFormResponses = EditFormResponses::__make_from_registered($provider);
        
        check($formManager->getCapabilities() === [
            'read_forms' => true,
            'edit_forms' => true,
            'read_form_responses' => true,
            'edit_form_responses' => true,
        ], 'Form Manager must declare all four requested capabilities.');
        check($formReader->getCapabilities() === [
            'read_forms' => true,
            'read_form_responses' => true,
        ], 'Form Reader must declare only the two read capabilities.');
        check(
            $formManager->isUserConfigurable() &&
                $formReader->isUserConfigurable() &&
                $readForms->isUserConfigurable() &&
                $editForms->isUserConfigurable() &&
                $readFormResponses->isUserConfigurable() &&
                $editFormResponses->isUserConfigurable(),
            'The form roles and capabilities must opt in to configuration in the Security interface.'
        );
        
        $manageReports = ConfigurableManageReports::__make_from_registered($provider);
        $manageArchive = FixedManageArchive::__make_from_registered($provider);
        $role = ConfigurableReportsEditor::__make_from_registered($provider);
        
        $container = new Container();
        $container->instance('meros.registers.capabilities', new class([$manageReports, $manageArchive]) {
            public function __construct(private array $capabilities) {}
        
            public function get(string $name): ?Capability {
                foreach ($this->capabilities as $capability) {
                    if ($capability->getIdentifier() === $name) {
                        return $capability;
                    }
                }
        
                return null;
            }
        });
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();
        $container->instance('meros.framework', $provider);
        $container->instance('meros.helpers.registers', new RegistersCollection());
        $settingsRegister = new SettingsRegister();
        $settingsContainers = new SettingsContainers();
        $container->instance('meros.registers.settings', $settingsRegister);
        $container->instance('meros.registers.settings_containers', $settingsContainers);
        $settingsContainers->checkout($provider)->register(MerosRoleSettings::class);
        
        $GLOBALS['roles'] = [];
        $GLOBALS['options'][Role::OPTION_NAME] = [
            'hidden_default_roles' => ['editor', 'administrator'],
            'role_capabilities' => [
                'reports_editor' => [
                    'read' => false,
                    'manage_reports' => false,
                    'manage_archive' => false,
                ],
            ],
        ];
        
        $role->register();
        $roleSettings = Role::resolveSettingsContainer($settingsContainers);
        check($roleSettings instanceof MerosRoleSettings, 'Role initialization must lazily resolve the registered role settings container.');
        check(
            Role::resolveSettingsContainer($settingsContainers->checkout(new RoleConfigurationProvider())) === $roleSettings,
            'Resolution must reuse the framework container, even when the register was checked out to another provider.'
        );
        $roleSettings->registerContainer();
        $legacySettings = $GLOBALS['options'][Role::OPTION_NAME];
        unset($GLOBALS['options'][Role::OPTION_NAME]);
        check(Role::getSettings() === [
            'hidden_default_roles' => [],
            'role_capabilities' => [],
        ], 'An unsaved role settings container must provide empty defaults.');
        $GLOBALS['options'][Role::OPTION_NAME] = $legacySettings;
        check(Role::getSettings() === $legacySettings, 'Existing option values must remain readable after resolving and caching the container.');
        $wordpressRole = get_role('reports_editor');
        check($wordpressRole instanceof TestWordPressRole, 'The theme/package-defined role must be created.');
        check(!$wordpressRole->has_cap('read'), 'Core capabilities may be overridden on an opted-in role.');
        check(!$wordpressRole->has_cap('manage_reports'), 'A user-configurable capability may be overridden for an opted-in role.');
        check($wordpressRole->has_cap('manage_archive'), 'A capability that did not opt in must retain its theme/package-defined default.');
        
        $GLOBALS['roles']['administrator'] = new TestWordPressRole([]);
        $GLOBALS['roles']['editor'] = new TestWordPressRole([]);
        $GLOBALS['roles']['author'] = new TestWordPressRole([]);
        $manageReports->register();
        $manageArchive->register();
        check(get_role('administrator')->has_cap('manage_reports'), 'Every registered capability must be granted to Administrator.');
        check(get_role('administrator')->has_cap('manage_archive'), 'Capabilities without additional role assignments must still be granted to Administrator.');
        check(get_role('editor')->has_cap('manage_reports'), 'A capability must be granted to built-in roles selected by the theme/package.');
        check(!get_role('editor')->has_cap('manage_archive'), 'Capabilities must not be added to unselected built-in roles.');
        check(!get_role('author')->has_cap('manage_reports'), 'Capabilities must not be added to unrelated built-in roles.');
        
        $editableRoles = array_fill_keys(Role::BUILT_IN_ROLES, []);
        $editableRoles = Role::filterEditableRoles($editableRoles);
        check(isset($editableRoles['administrator']), 'Administrator must remain available even if it is present in stored settings.');
        check(!isset($editableRoles['editor']), 'A hidden built-in role must be removed from editable role selectors.');
        check(isset($editableRoles['author']), 'Unhidden built-in roles must remain available.');
        
        try {
            InvalidAdministratorRole::__make_from_registered($provider);
            throw new RuntimeException('The administrator role must not be configurable as a custom role.');
        } catch (InvalidArgumentException $exception) {
            check(str_contains($exception->getMessage(), 'built-in WordPress role'), 'The built-in role error should explain the restriction.');
        }
        
        Facade::clearResolvedInstances();
        
        $capabilitiesRegister = new CapabilitiesRegister();
        $rolesRegister = new RolesRegister();
        $container->instance('meros.registers.capabilities', $capabilitiesRegister);
        $container->instance('meros.registers.roles', $rolesRegister);
        
        FormSecurityOrchestrator::create($provider);
        
        check($capabilitiesRegister->all()->count() === 4, 'The Security orchestrator must register all four form capabilities.');
        check($rolesRegister->all()->count() === 2, 'The Security orchestrator must register both form roles.');
        
        foreach (Role::BUILT_IN_ROLES as $handle) {
            $GLOBALS['roles'][$handle] = new TestWordPressRole(['read' => true, 'edit_posts' => true]);
        }
        foreach ($capabilitiesRegister->all() as $capability) {
            $capability->register();
        }
        
        $GLOBALS['options'][Role::OPTION_NAME]['role_capabilities'] = [
            'administrator' => ['read' => false],
            'editor' => ['edit_posts' => false, 'upload_files' => true, 'read_forms' => true, 'read_form_responses' => true],
            'form_reader' => ['read' => true, 'upload_files' => true, 'edit_forms' => true],
        ];
        Role::applyBuiltInCapabilitySettings();
        $reader = $rolesRegister->get('form_reader');
        $reader->register();
        check(!get_role('editor')->has_cap('edit_posts'), 'Built-in roles must support revoking core capabilities.');
        check(get_role('editor')->has_cap('upload_files'), 'Built-in roles must support granting core capabilities.');
        check(get_role('administrator')->has_cap('read'), 'Stored overrides must never modify Administrator.');
        check(get_role('editor')->has_cap('read_forms'), 'Editors must retain their opted-in form access.');
        check(get_role('editor')->has_cap('read_form_responses'), 'Opted-in response capabilities must be assignable to default roles.');
        check(get_role('form_reader')->has_cap('read'), 'Core capabilities not declared by a custom role can be added.');
        check(get_role('form_reader')->has_cap('upload_files'), 'Additional core capabilities must be applied on initialization.');
        check(!get_role('form_reader')->has_cap('edit_forms'), 'Undeclared theme/package-defined capabilities must remain protected.');
        $reader->userConfigurable(false);
        check(Role::getConfigurableCapabilities('form_reader') === [], 'Custom roles must opt in to core capability editing.');
        $reader->userConfigurable();
        check(Role::getConfigurableCapabilities('administrator') === [], 'Administrator must have no editable capabilities.');
    }

    public function testPermissionsMatrixSavingAndValidation(): void {
        check(class_exists(RolesRegister::class), 'The role register must be available to providers.');
        check(class_exists(CapabilitiesRegister::class), 'The capability register must be available to providers.');
        
        $provider = new RoleConfigurationProvider();
        $formManager = FormManager::__make_from_registered($provider);
        $formReader = FormReader::__make_from_registered($provider);
        $readForms = ReadForms::__make_from_registered($provider);
        $editForms = EditForms::__make_from_registered($provider);
        $readFormResponses = ReadFormResponses::__make_from_registered($provider);
        $editFormResponses = EditFormResponses::__make_from_registered($provider);
        
        check($formManager->getCapabilities() === [
            'read_forms' => true,
            'edit_forms' => true,
            'read_form_responses' => true,
            'edit_form_responses' => true,
        ], 'Form Manager must declare all four requested capabilities.');
        check($formReader->getCapabilities() === [
            'read_forms' => true,
            'read_form_responses' => true,
        ], 'Form Reader must declare only the two read capabilities.');
        check(
            $formManager->isUserConfigurable() &&
                $formReader->isUserConfigurable() &&
                $readForms->isUserConfigurable() &&
                $editForms->isUserConfigurable() &&
                $readFormResponses->isUserConfigurable() &&
                $editFormResponses->isUserConfigurable(),
            'The form roles and capabilities must opt in to configuration in the Security interface.'
        );
        
        $manageReports = ConfigurableManageReports::__make_from_registered($provider);
        $manageArchive = FixedManageArchive::__make_from_registered($provider);
        $role = ConfigurableReportsEditor::__make_from_registered($provider);
        
        $container = new Container();
        $container->instance('meros.registers.capabilities', new class([$manageReports, $manageArchive]) {
            public function __construct(private array $capabilities) {}
        
            public function get(string $name): ?Capability {
                foreach ($this->capabilities as $capability) {
                    if ($capability->getIdentifier() === $name) {
                        return $capability;
                    }
                }
        
                return null;
            }
        });
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();
        $container->instance('meros.framework', $provider);
        $container->instance('meros.helpers.registers', new RegistersCollection());
        $settingsRegister = new SettingsRegister();
        $settingsContainers = new SettingsContainers();
        $container->instance('meros.registers.settings', $settingsRegister);
        $container->instance('meros.registers.settings_containers', $settingsContainers);
        $settingsContainers->checkout($provider)->register(MerosRoleSettings::class);
        
        $GLOBALS['roles'] = [];
        $GLOBALS['options'][Role::OPTION_NAME] = [
            'hidden_default_roles' => ['editor', 'administrator'],
            'role_capabilities' => [
                'reports_editor' => [
                    'read' => false,
                    'manage_reports' => false,
                    'manage_archive' => false,
                ],
            ],
        ];
        
        $role->register();
        $roleSettings = Role::resolveSettingsContainer($settingsContainers);
        check($roleSettings instanceof MerosRoleSettings, 'Role initialization must lazily resolve the registered role settings container.');
        check(
            Role::resolveSettingsContainer($settingsContainers->checkout(new RoleConfigurationProvider())) === $roleSettings,
            'Resolution must reuse the framework container, even when the register was checked out to another provider.'
        );
        $roleSettings->registerContainer();
        $legacySettings = $GLOBALS['options'][Role::OPTION_NAME];
        unset($GLOBALS['options'][Role::OPTION_NAME]);
        check(Role::getSettings() === [
            'hidden_default_roles' => [],
            'role_capabilities' => [],
        ], 'An unsaved role settings container must provide empty defaults.');
        $GLOBALS['options'][Role::OPTION_NAME] = $legacySettings;
        check(Role::getSettings() === $legacySettings, 'Existing option values must remain readable after resolving and caching the container.');
        $wordpressRole = get_role('reports_editor');
        check($wordpressRole instanceof TestWordPressRole, 'The theme/package-defined role must be created.');
        check(!$wordpressRole->has_cap('read'), 'Core capabilities may be overridden on an opted-in role.');
        check(!$wordpressRole->has_cap('manage_reports'), 'A user-configurable capability may be overridden for an opted-in role.');
        check($wordpressRole->has_cap('manage_archive'), 'A capability that did not opt in must retain its theme/package-defined default.');
        
        $GLOBALS['roles']['administrator'] = new TestWordPressRole([]);
        $GLOBALS['roles']['editor'] = new TestWordPressRole([]);
        $GLOBALS['roles']['author'] = new TestWordPressRole([]);
        $manageReports->register();
        $manageArchive->register();
        check(get_role('administrator')->has_cap('manage_reports'), 'Every registered capability must be granted to Administrator.');
        check(get_role('administrator')->has_cap('manage_archive'), 'Capabilities without additional role assignments must still be granted to Administrator.');
        check(get_role('editor')->has_cap('manage_reports'), 'A capability must be granted to built-in roles selected by the theme/package.');
        check(!get_role('editor')->has_cap('manage_archive'), 'Capabilities must not be added to unselected built-in roles.');
        check(!get_role('author')->has_cap('manage_reports'), 'Capabilities must not be added to unrelated built-in roles.');
        
        $editableRoles = array_fill_keys(Role::BUILT_IN_ROLES, []);
        $editableRoles = Role::filterEditableRoles($editableRoles);
        check(isset($editableRoles['administrator']), 'Administrator must remain available even if it is present in stored settings.');
        check(!isset($editableRoles['editor']), 'A hidden built-in role must be removed from editable role selectors.');
        check(isset($editableRoles['author']), 'Unhidden built-in roles must remain available.');
        
        try {
            InvalidAdministratorRole::__make_from_registered($provider);
            throw new RuntimeException('The administrator role must not be configurable as a custom role.');
        } catch (InvalidArgumentException $exception) {
            check(str_contains($exception->getMessage(), 'built-in WordPress role'), 'The built-in role error should explain the restriction.');
        }
        
        Facade::clearResolvedInstances();
        
        $capabilitiesRegister = new CapabilitiesRegister();
        $rolesRegister = new RolesRegister();
        $container->instance('meros.registers.capabilities', $capabilitiesRegister);
        $container->instance('meros.registers.roles', $rolesRegister);
        
        FormSecurityOrchestrator::create($provider);
        
        check($capabilitiesRegister->all()->count() === 4, 'The Security orchestrator must register all four form capabilities.');
        check($rolesRegister->all()->count() === 2, 'The Security orchestrator must register both form roles.');
        
        foreach (Role::BUILT_IN_ROLES as $handle) {
            $GLOBALS['roles'][$handle] = new TestWordPressRole(['read' => true, 'edit_posts' => true]);
        }
        foreach ($capabilitiesRegister->all() as $capability) {
            $capability->register();
        }
        
        $GLOBALS['options'][Role::OPTION_NAME]['role_capabilities'] = [
            'administrator' => ['read' => false],
            'editor' => ['edit_posts' => false, 'upload_files' => true, 'read_forms' => true, 'read_form_responses' => true],
            'form_reader' => ['read' => true, 'upload_files' => true, 'edit_forms' => true],
        ];
        Role::applyBuiltInCapabilitySettings();
        $reader = $rolesRegister->get('form_reader');
        $reader->register();
        check(!get_role('editor')->has_cap('edit_posts'), 'Built-in roles must support revoking core capabilities.');
        check(get_role('editor')->has_cap('upload_files'), 'Built-in roles must support granting core capabilities.');
        check(get_role('administrator')->has_cap('read'), 'Stored overrides must never modify Administrator.');
        check(get_role('editor')->has_cap('read_forms'), 'Editors must retain their opted-in form access.');
        check(get_role('editor')->has_cap('read_form_responses'), 'Opted-in response capabilities must be assignable to default roles.');
        check(get_role('form_reader')->has_cap('read'), 'Core capabilities not declared by a custom role can be added.');
        check(get_role('form_reader')->has_cap('upload_files'), 'Additional core capabilities must be applied on initialization.');
        check(!get_role('form_reader')->has_cap('edit_forms'), 'Undeclared theme/package-defined capabilities must remain protected.');
        $reader->userConfigurable(false);
        check(Role::getConfigurableCapabilities('form_reader') === [], 'Custom roles must opt in to core capability editing.');
        $reader->userConfigurable();
        check(Role::getConfigurableCapabilities('administrator') === [], 'Administrator must have no editable capabilities.');
        
        $page = (new ReflectionClass(MerosSecurity::class))->newInstanceWithoutConstructor();
        $html = (new ReflectionMethod(MerosSecurity::class, 'getPermissionsTabContent'))->invoke($page);
        $document = new DOMDocument();
        check($document->loadHTML($html), 'The matrix must render valid HTML.');
        $xpath = new DOMXPath($document);
        check($xpath->query('//input[@id="capability-read-administrator"][@disabled][@checked]')->length === 1, 'Administrator core capabilities must be read-only and reflect actual grants.');
        check($xpath->query('//input[@id="capability-edit_posts-editor"][not(@disabled)][not(@checked)]')->length === 1, 'Editable built-in cells must show persisted revocations.');
        check($xpath->query('//input[@id="capability-upload_files-form_reader"][not(@disabled)][@checked]')->length === 1, 'Opted-in custom roles must expose undeclared core capabilities.');
        check($xpath->query('//input[@id="capability-edit_forms-form_reader"][@disabled]')->length === 1, 'Undeclared theme/package-defined capability cells must stay locked.');
        foreach (['editor', 'author', 'contributor', 'subscriber'] as $roleHandle) {
            foreach (['read_forms', 'edit_forms', 'read_form_responses', 'edit_form_responses'] as $capability) {
                check($xpath->query('//input[@id="capability-' . $capability . '-' . $roleHandle . '"][not(@disabled)]')->length === 1, 'All form capability cells must be editable for default roles.');
            }
        }
        
        $GLOBALS['canManage'] = true;
        $GLOBALS['validNonce'] = true;
        $GLOBALS['options'][Role::OPTION_NAME]['unrelated_setting'] = 'preserve_me';
        $settingUpdates = [];
        $roleSettings->onUpdate(function (mixed $value, mixed $oldValue, string $name) use (&$settingUpdates) {
            $settingUpdates[] = $value;
            return $value;
        });
        $_POST = [
            'meros_role_settings_complete' => '1',
            'meros_hidden_default_roles' => ['editor', 'administrator'],
            'meros_role_capabilities' => [
                'administrator' => ['read' => '0'],
                'editor' => ['read' => '1', 'read_forms' => '1', 'read_form_responses' => '1', 'unknown_capability' => '1'],
                'subscriber' => ['read' => '1', 'read_forms' => '1', 'edit_forms' => '1'],
                'form_reader' => ['read' => '1', 'edit_posts' => '1', 'read_forms' => '1', 'edit_forms' => '1'],
            ],
        ];
        try {
            $page->saveRoleSettings();
            throw new RuntimeException('Saving must redirect.');
        } catch (TestRoleSettingsRedirect $redirect) {
            check(str_contains($redirect->getMessage(), 'roles-saved=1'), 'A successful save must redirect with confirmation.');
        }
        check(get_role('form_reader')->has_cap('edit_posts'), 'New core grants must apply immediately after saving.');
        check(!get_role('editor')->has_cap('upload_files'), 'Unchecked core capabilities must be revoked immediately.');
        check(!get_role('form_reader')->has_cap('upload_files'), 'Unchecked extra core grants must be revocable on custom roles.');
        check(get_role('administrator')->has_cap('read'), 'Forged Administrator submissions must not change its grants.');
        check(get_role('administrator')->has_cap('read_forms'), 'Administrator must retain every registered theme/package-defined capability.');
        check(!get_role('editor')->has_cap('unknown_capability'), 'Unknown capability submissions must be ignored.');
        check(get_role('editor')->has_cap('read_forms'), 'Editors must be able to retain their opted-in form access.');
        check(get_role('editor')->has_cap('read_form_responses'), 'Opted-in response assignments to default roles must apply immediately.');
        check(get_role('subscriber')->has_cap('read_forms') && get_role('subscriber')->has_cap('edit_forms'), 'Default roles must accept the form capabilities through the permissions page.');
        check(!get_role('form_reader')->has_cap('edit_forms'), 'Forged undeclared theme/package-defined grants must be ignored.');
        check(Role::getSettings()['hidden_default_roles'] === ['editor'], 'Administrator must not be hidden through a forged request.');
        check(count($settingUpdates) === 2, 'The permissions save must run the container update lifecycle for both settings.');
        check(Role::getSettings()['unrelated_setting'] === 'preserve_me', 'Saving permissions must preserve other values in the container.');
        check(Role::getSettings()['role_capabilities']['editor']['upload_files'] === false, 'Container sanitization must retain boolean revocations and role/capability keys.');
        Role::applyBuiltInCapabilitySettings();
        $reader->register();
        check(get_role('form_reader')->has_cap('edit_posts') && !get_role('editor')->has_cap('upload_files'), 'Saved choices must survive subsequent role initialization.');
        
        $before = $GLOBALS['options'];
        unset($_POST['meros_role_settings_complete']);
        try {
            $page->saveRoleSettings();
            throw new LogicException('Incomplete matrix submissions must be rejected.');
        } catch (RuntimeException $exception) {
            check(str_contains($exception->getMessage(), 'incomplete'), 'The missing end marker must produce an explicit error.');
        }
        check($GLOBALS['options'] === $before, 'Incomplete submissions must not save any changes.');
        
        $_POST['meros_role_settings_complete'] = '1';
        $GLOBALS['canManage'] = false;
        try {
            $page->saveRoleSettings();
            throw new LogicException('Users without manage_options must not save role settings.');
        } catch (RuntimeException $exception) {
            check(str_contains($exception->getMessage(), 'permission'), 'Unauthorized saves must be rejected explicitly.');
        }
        $GLOBALS['canManage'] = true;
        $GLOBALS['validNonce'] = false;
        try {
            $page->saveRoleSettings();
            throw new LogicException('Invalid nonces must not save role settings.');
        } catch (RuntimeException $exception) {
            check(str_contains($exception->getMessage(), 'nonce'), 'Invalid nonces must be rejected.');
        }
        check($GLOBALS['options'] === $before, 'Unauthorized saves must not modify stored settings.');
        
        $GLOBALS['validNonce'] = true;
        $reader->userConfigurable(false);
        $_POST['meros_role_capabilities']['form_reader']['manage_options'] = '1';
        try {
            $page->saveRoleSettings();
            throw new LogicException('Saving must redirect.');
        } catch (TestRoleSettingsRedirect $redirect) {}
        check(!get_role('form_reader')->has_cap('manage_options'), 'Forged core grants to opted-out custom roles must not be applied.');
        
        $GLOBALS['failOptionUpdates'] = true;
        $_POST['meros_hidden_default_roles'] = [];
        $before = $GLOBALS['options'];
        try {
            $page->saveRoleSettings();
            throw new LogicException('Failed persistence must not report success.');
        } catch (RuntimeException $exception) {
            check(str_contains($exception->getMessage(), 'could not be saved'), 'A failed container write must be reported explicitly, not hidden by its cache.');
        }
        check($GLOBALS['options'] === $before, 'Failed container writes must leave persistent settings unchanged.');
    }

}
