# Roles and capabilities

The Security > Manage Permissions page displays a capability-by-role matrix. Users cannot create roles or capability definitions through the interface. Administrator is read-only. Other built-in roles can have their WordPress capabilities edited and can be hidden from new role-assignment selectors. Existing assignments and capabilities are not changed when a role is hidden.

The WordPress section includes the primitive capabilities assigned by core's default role setup, including legacy `level_*` capabilities. These can also be granted to custom roles that opt in with `userConfigurable()`, even when the role did not declare them in its defaults. The catalogue is independent of current role assignments, so removing a capability does not remove its row. Context-dependent meta capabilities (such as `edit_post`) are not role grants and are not listed.

Saving applies changes immediately to all users with the affected role and retains them on subsequent requests. Administrator's existing WordPress capabilities are never changed by the editor. Powerful grants such as `manage_options` and `promote_users` can confer administrative access; multisite and WordPress configuration may impose additional restrictions.

## Defining a role and capabilities

Create feature classes in a theme or package, then instantiate them through that provider's registers:

```php
use MM\Meros\Contracts\Features\Security\Capability;
use MM\Meros\Contracts\Features\Security\Role;

class ManageReports extends Capability {
    protected function configure(): void {
        $this->name('manage_reports');
        $this->label('Manage reports');
        $this->description('View and manage reports.');
        $this->roles(['editor']);
        $this->userConfigurable();
    }
}

class ReportsEditor extends Role {
    protected function configure(): void {
        $this->handle('reports_editor');
        $this->label('Reports Editor');
        $this->capabilities([
            'read' => true,
            'manage_reports' => true,
        ]);
        $this->userConfigurable();
    }
}
```

In the provider, register both definitions:

```php
$this->capabilities(ManageReports::class)->make();
$this->roles(ReportsEditor::class)->make();
```

Role capabilities default to the values declared by the theme/package. For theme/package-defined capabilities, a checkbox is editable only when the role declares that capability and both the role and capability call `userConfigurable()`. Core WordPress capabilities require only the custom role's opt-in. User changes are stored through the framework-owned `MerosRoleSettings` settings container and applied when WordPress initializes the role. `Role::resolveSettingsContainer()` resolves this container through the settings-container register, following the same lazy resolution pattern as blocks. Its underlying option remains `meros_role_settings`, with `hidden_default_roles` and `role_capabilities` settings, so previously saved permissions are preserved. Theme/package-defined capabilities not opted in remain code-controlled.

Every registered capability is automatically granted to the Administrator role. Use `roles([...])` on a capability to also grant it to one or more other built-in WordPress roles. These assignments are theme/package-defined and shown read-only in the permissions interface.
