<?php

namespace MM\Meros\Contracts\Features\Security;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsHookable;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Facades\Admin\SettingsContainers as SettingsContainersFacade;
use MM\Meros\Facades\Framework;
use MM\Meros\Facades\Security\Capabilities as CapabilitiesFacade;
use MM\Meros\Facades\Security\Roles as RolesFacade;

class Role extends Feature implements Makeable, Registrable {
    public const OPTION_NAME = 'meros_role_settings';

    public const BUILT_IN_ROLES = [
        'administrator',
        'editor',
        'author',
        'contributor',
        'subscriber',
    ];

    /**
     * The role's handle (identifier).
     *
     * @var string
     */
    protected string $handle = '';

    /**
     * The role's theme/package-defined capability defaults.
     *
     * @var array
     */
    protected array $capabilities = [];

    /**
     * Whether the role is user-configurable.
     *
     * @var boolean
     */
    protected bool $userConfigurable = false;

    use IsMakeable, IsRegistrable, IsHookable;

    protected function init(): void {
        $this->identifier('handle', 'snake');
        $this->setHook('init', [$this, 'register']);
        $this->hook();
    }

    protected function whenConfigured(): void {
        if ($this->handle === '') {
            throw new \InvalidArgumentException('A role handle must be set.');
        }

        if (!preg_match('/^[a-z0-9_]+$/', $this->handle)) {
            throw new \InvalidArgumentException('Role handles may contain only lowercase letters, numbers, and underscores.');
        }

        if (in_array($this->handle, self::BUILT_IN_ROLES, true)) {
            throw new \InvalidArgumentException("The built-in WordPress role '{$this->handle}' cannot be registered as a custom role.");
        }
    }

    /**
     * Sets the role's handle (identifier).
     *
     * @param string $handle
     *
     * @return static
     */
    final public function handle(string $handle): static {
        $handle = strtolower(\Illuminate\Support\Str::snake($handle));

        if (!preg_match('/^[a-z0-9_]+$/', $handle)) {
            throw new \InvalidArgumentException('Role handles may contain only lowercase letters, numbers, and underscores.');
        }

        $this->setIdentifier($handle, false);
        return $this;
    }

    /**
     * Defines the role's theme/package-defined capability defaults.
     *
     * @param array<string, bool>|list<string> $capabilities
     */
    final public function capabilities(array $capabilities): static {
        $this->capabilities = [];

        foreach ($capabilities as $name => $enabled) {
            if (is_int($name)) {
                if (!is_string($enabled)) {
                    throw new \InvalidArgumentException('Role capabilities must be capability names or a name-to-boolean map.');
                }

                $this->capabilities[$enabled] = true;
                continue;
            }

            if (!is_string($name) || !is_bool($enabled)) {
                throw new \InvalidArgumentException('Role capability values must be booleans.');
            }

            $this->capabilities[$name] = $enabled;
        }

        foreach (array_keys($this->capabilities) as $capability) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $capability)) {
                throw new \InvalidArgumentException("Invalid capability name '{$capability}'.");
            }
        }

        return $this;
    }

    /**
     * Sets whether the role is user-configurable.
     *
     * @param boolean $userConfigurable
     *
     * @return static
     */
    final public function userConfigurable(bool $userConfigurable = true): static {
        $this->userConfigurable = $userConfigurable;
        return $this;
    }

    /**
     * Returns the role's theme/package-defined capability defaults.
     *
     * @return array
     */
    final public function getCapabilities(): array {
        return $this->capabilities;
    }

    /**
     * Returns whether the role is user-configurable.
     *
     * @return boolean
     */
    final public function isUserConfigurable(): bool {
        return $this->userConfigurable;
    }

    /**
     * Registers the role with WordPress, applying capability defaults and user-configurable overrides.
     *
     * @return void
     */
    final public function register(): void {
        $handle = $this->getIdentifier();
        $settings = self::getSettings();
        $roleConfigurations = $settings['role_capabilities'] ?? [];

        $overrides = is_array($roleConfigurations)
            ? ($roleConfigurations[$handle] ?? [])
            : [];
            
        $role = \get_role($handle);

        if (!$role) {
            \add_role($handle, $this->getLabel(), []);
            $role = \get_role($handle);
        }

        if (!$role) {
            throw new \RuntimeException("WordPress could not register the '{$handle}' role.");
        }

        $capabilities = $this->capabilities;
        if ($this->userConfigurable && is_array($overrides)) {
            foreach (Capability::CORE_CAPABILITIES as $capability) {
                if (isset($overrides[$capability]) && is_bool($overrides[$capability])) {
                    $capabilities[$capability] = $overrides[$capability];
                }
            }
        }

        foreach ($capabilities as $capability => $default) {
            $enabled = $default;

            if (
                $this->userConfigurable &&
                is_array($overrides) &&
                array_key_exists($capability, $overrides) &&
                is_bool($overrides[$capability]) &&
                (in_array($capability, Capability::CORE_CAPABILITIES, true) ||
                    $this->isCapabilityUserConfigurable($capability))
            ) {
                $enabled = $overrides[$capability];
            }

            if ($enabled && !$role->has_cap($capability)) {
                $role->add_cap($capability);
            } elseif (!$enabled && $role->has_cap($capability)) {
                $role->remove_cap($capability);
            }
        }
    }

    /**
     * Retrieves the list of capabilities that can be configured by the user for the specified role handle.
     *
     * @param string $handle
     *
     * @return array
     */
    public static function getConfigurableCapabilities(string $handle): array {
        if ($handle === 'administrator') {
            return [];
        }

        if (in_array($handle, self::BUILT_IN_ROLES, true)) {
            $capabilities = Capability::CORE_CAPABILITIES;
            foreach (CapabilitiesFacade::all() as $capability) {
                if ($capability instanceof Capability &&
                    $capability->isUserConfigurable() &&
                    $capability->isAssignableToDefaultRoles()
                ) {
                    $capabilities[] = $capability->getIdentifier();
                }
            }

            return array_values(array_unique($capabilities));
        }

        $role = RolesFacade::get($handle);
        if (!($role instanceof self) || !$role->isUserConfigurable()) {
            return [];
        }

        $capabilities = Capability::CORE_CAPABILITIES;
        foreach (CapabilitiesFacade::all() as $capability) {
            if ($capability instanceof Capability &&
                $capability->isUserConfigurable() &&
                array_key_exists($capability->getIdentifier(), $role->getCapabilities())
            ) {
                $capabilities[] = $capability->getIdentifier();
            }
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Applies core and opted-in custom capability settings to default roles except Administrator.
     *
     * @return void
     */
    public static function applyBuiltInCapabilitySettings(): void {
        $configurations = self::getSettings()['role_capabilities'] ?? [];
        if (!is_array($configurations)) {
            throw new \RuntimeException('Stored role capability settings must be an array.');
        }

        foreach (array_diff(self::BUILT_IN_ROLES, ['administrator']) as $handle) {
            $overrides = $configurations[$handle] ?? [];
            $role = \get_role($handle);
            if (!$role || !is_array($overrides)) {
                continue;
            }

            foreach (self::getConfigurableCapabilities($handle) as $capability) {
                if (!array_key_exists($capability, $overrides) || !is_bool($overrides[$capability])) {
                    continue;
                }

                if ($overrides[$capability] && !$role->has_cap($capability)) {
                    $role->add_cap($capability);
                } elseif (!$overrides[$capability] && $role->has_cap($capability)) {
                    $role->remove_cap($capability);
                }
            }
        }
    }

    /**
     * Resolves the settings container for role settings from the provided register.
     *
     * @param SettingsContainers $register
     *
     * @return SettingsContainer
     */
    final public static function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        $provider = Framework::get();
        $container = $register->checkout($provider)->get(self::OPTION_NAME);

        if ($container === null) {
            $container = $register->checkout($provider)->makeFrom(self::OPTION_NAME);
        }

        if (!($container instanceof SettingsContainer)) {
            throw new \LogicException('The resolved role settings container must be an instance of SettingsContainer.');
        }

        return $container;
    }

    /**
     * Retrieves the current role settings as an array.
     *
     * @return array
     */
    public static function getSettings(): array {
        return self::resolveSettingsContainer(SettingsContainersFacade::instance())->getValue(true);
    }

    /**
     * Filters out roles that are not editable based on the current role settings.
     *
     * @param array $roles
     *
     * @return array
     */
    public static function filterEditableRoles(array $roles): array {
        $hiddenRoles = self::getSettings()['hidden_default_roles'] ?? [];

        if (!is_array($hiddenRoles)) {
            return $roles;
        }

        foreach (array_diff(self::BUILT_IN_ROLES, ['administrator']) as $handle) {
            if (in_array($handle, $hiddenRoles, true)) {
                unset($roles[$handle]);
            }
        }

        return $roles;
    }

    /**
     * Determines if the specified capability is configurable by the user.
     *
     * @param string $name
     *
     * @return boolean
     */
    private function isCapabilityUserConfigurable(string $name): bool {
        $capability = CapabilitiesFacade::get($name);
        return $capability instanceof Capability && $capability->isUserConfigurable();
    }
}
