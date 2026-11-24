<?php

namespace MM\Meros\Contracts\Features\Security;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsHookable;

class Capability extends Feature implements Makeable, Registrable {
    // Primitive grants from WordPress's default role setup, not context-dependent meta capabilities.
    public const CORE_CAPABILITIES = [
        'activate_plugins', 'create_users', 'delete_others_pages', 'delete_others_posts',
        'delete_pages', 'delete_plugins', 'delete_posts', 'delete_private_pages',
        'delete_private_posts', 'delete_published_pages', 'delete_published_posts',
        'delete_themes', 'delete_users', 'edit_dashboard', 'edit_files',
        'edit_others_pages', 'edit_others_posts', 'edit_pages', 'edit_plugins',
        'edit_posts', 'edit_private_pages', 'edit_private_posts', 'edit_published_pages',
        'edit_published_posts', 'edit_theme_options', 'edit_themes', 'edit_users',
        'export', 'import', 'install_plugins', 'install_themes', 'list_users',
        'manage_categories', 'manage_links', 'manage_options', 'moderate_comments',
        'promote_users', 'publish_pages', 'publish_posts', 'read', 'read_private_pages',
        'read_private_posts', 'remove_users', 'switch_themes', 'unfiltered_html',
        'unfiltered_upload', 'update_core', 'update_plugins', 'update_themes', 'upload_files'
    ];

    /**
     * The name of the capability.
     *
     * @var string
     */
    protected string $name = '';

    /**
     * The roles associated with this capability.
     *
     * @var array
     */
    protected array $roles = [];

    /**
     * Indicates if the capability is user configurable.
     *
     * @var bool
     */
    protected bool $userConfigurable = false;

    use IsMakeable, IsRegistrable, IsHookable;

    protected function init(): void {
        $this->identifier('name', 'snake');
        $this->setHook('init', [$this, 'register']);
        $this->hook();
    }

    protected function whenConfigured(): void {
        if ($this->name === '' || !preg_match('/^[a-z0-9_]+$/', $this->name)) {
            throw new \InvalidArgumentException('Capability names may contain only lowercase letters, numbers, and underscores.');
        }

        $this->validateRoles($this->roles);
    }

    /**
     * Sets the name of the capability.
     *
     * @param string $name The name of the capability.
     *
     * @return static
     */
    final public function name(string $name): static {
        $name = strtolower(\Illuminate\Support\Str::snake($name));

        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('Capability names may contain only letters, numbers, and underscores.');
        }

        $this->setIdentifier($name, false);
        return $this;
    }

    /**
     * Sets the roles associated with the capability.
     *
     * @param array $roles The roles to associate with the capability.
     *
     * @return static
     */
    final public function roles(array $roles): static {
        $this->validateRoles($roles);
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    /**
     * Retrieves the roles associated with the capability.
     *
     * @return array
     */
    final public function getRoles(): array {
        return $this->roles;
    }

    /**
     * Sets whether the capability is user configurable.
     *
     * @param bool $userConfigurable
     *
     * @return static
     */
    final public function userConfigurable(bool $userConfigurable = true): static {
        $this->userConfigurable = $userConfigurable;
        return $this;
    }

    /**
     * Determines if the capability is user configurable.
     *
     * @return bool
     */
    final public function isUserConfigurable(): bool {
        return $this->userConfigurable;
    }

    /**
     * Registers the capability with the associated roles in WordPress.
     *
     * @return void
     */
    final public function register(): void {
        $roles = array_unique(array_merge(['administrator'], $this->roles));

        foreach ($roles as $roleName) {
            $role = \get_role($roleName);
            if (!$role) {
                throw new \RuntimeException("The '{$roleName}' WordPress role is unavailable for capability '{$this->name}'.");
            }

            if (!$role->has_cap($this->name)) {
                $role->add_cap($this->name);
            }
        }
    }

    /**
     * Validates the provided roles for the capability.
     *
     * @param array $roles
     *
     * @return void
     */
    private function validateRoles(array $roles): void {
        if (!array_is_list($roles)) {
            throw new \InvalidArgumentException('Capability roles must be provided as a list of WordPress role handles.');
        }

        foreach ($roles as $roleName) {
            if (!is_string($roleName) || !in_array($roleName, Role::BUILT_IN_ROLES, true)) {
                throw new \InvalidArgumentException('Capabilities may only be associated with built-in WordPress roles.');
            }
        }
    }
}
