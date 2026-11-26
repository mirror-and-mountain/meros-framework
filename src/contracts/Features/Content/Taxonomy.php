<?php

namespace MM\Meros\Contracts\Features\Content;

use Illuminate\Support\Str;
use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsHookable;

class Taxonomy extends Feature implements Makeable, Registrable {
    use IsMakeable, IsRegistrable, IsHookable;

    /**
     * The taxonomy's handle (name).
     *
     * @var string
     */
    protected string $handle = '';

    /**
     * The taxonomy's singular label.
     *
     * @var string
     */
    protected string $singularLabel = '';

    /**
     * The taxonomy's plural label.
     *
     * @var string
     */
    protected string $pluralLabel = '';

    /**
     * Arguments for registering the taxonomy with WordPress.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * Explicit label overrides retained when singular and plural labels change.
     *
     * @var array
     */
    private array $labelOverrides = [];

    /**
     * The post type handles associated with this taxonomy.
     *
     * @var array<string>
     */
    protected array $postTypes = [];

    /**
     * Whether the taxonomy is a core taxonomy managed by WordPress.
     *
     * @var bool
     */
    protected bool $isCore = false;

    /**
     * Initialises the taxonomy's identifier and registration hook.
     *
     * @return void
     */
    final protected function init(): void {
        $this->identifier('handle', 'slug');
        // Post types register at priority 10, including those configured after this taxonomy.
        $this->setHook('init', [$this, 'register'], 11);
        $this->hook();
    }

    /**
     * Infers an unset class-based handle and initialises the taxonomy's labels.
     *
     * @return void
     */
    final protected function whenConfigured(): void {
        if ($this->creationMethod === 'made_from_class' && $this->handle === '') {
            $this->handle(Str::slug(class_basename($this)));
        }

        if ($this->singularLabel === '') {
            $this->label(Str::title(Str::replace(['-', '_'], ' ', $this->handle)));
        }

        do_action("meros_after_taxonomy_configured_{$this->getHandle()}");
    }

    /**
     * Registers the taxonomy and associates its post types without replacing an existing taxonomy.
     *
     * @return void
     * @throws \InvalidArgumentException If the handle is invalid.
     * @throws \RuntimeException If WordPress rejects the registration or association.
     */
    final public function register(): void {
        if (!preg_match('/^[a-z0-9_-]{1,32}$/', $this->handle)) {
            throw new \InvalidArgumentException('Taxonomy handles must contain 1-32 lowercase letters, numbers, underscores or hyphens.');
        }

        if (!$this->isCore && !taxonomy_exists($this->handle)) {
            $result = register_taxonomy($this->handle, $this->postTypes, array_merge(
                $this->args,
                ['description' => $this->getDescription()]
            ));

            if (is_wp_error($result)) {
                throw new \RuntimeException("Failed to register taxonomy '{$this->handle}': " . $result->get_error_message());
            }
        }

        foreach ($this->postTypes as $postType) {
            $this->associatePostType($postType);
        }
    }

    /**
     * Sets the taxonomy's handle.
     *
     * @param string $handle
     * @return static
     */
    final public function handle(string $handle): static {
        return $this->setIdentifier($handle, false);
    }

    /**
     * Sets the taxonomy's handle (alias for handle).
     *
     * @param string $name
     * @return static
     */
    final public function name(string $name): static {
        return $this->handle($name);
    }

    /**
     * Marks the taxonomy as core, preventing registration by the framework.
     *
     * @param bool $isCore
     * @return static
     */
    final public function core(bool $isCore = true): static {
        $this->isCore = $isCore;
        return $this;
    }

    /**
     * Adds a post type without replacing existing associations.
     *
     * @param string $postType
     * @return static
     * @throws \InvalidArgumentException If the post type handle is empty.
     * @throws \RuntimeException If WordPress rejects an association.
     */
    final public function postType(string $postType): static {
        if ($postType === '') {
            throw new \InvalidArgumentException('A taxonomy post type must have a non-empty handle.');
        }

        if (!in_array($postType, $this->postTypes, true)) {
            $this->postTypes[] = $postType;
        }

        $this->associatePostType($postType);
        return $this;
    }

    /**
     * Adds multiple post types to the taxonomy.
     *
     * @param array<string> $postTypes
     * @return static
     */
    final public function postTypes(array $postTypes): static {
        foreach ($postTypes as $postType) {
            $this->postType($postType);
        }

        return $this;
    }

    /**
     * Associates a post type once both it and the taxonomy exist in WordPress.
     *
     * @param string $postType
     * @return void
     * @throws \RuntimeException If WordPress rejects the association.
     */
    private function associatePostType(string $postType): void {
        if (!taxonomy_exists($this->handle) || !post_type_exists($postType)) {
            return;
        }

        if (!register_taxonomy_for_object_type($this->handle, $postType)) {
            throw new \RuntimeException("Failed to associate taxonomy '{$this->handle}' with post type '{$postType}'.");
        }
    }

    /**
     * Sets the singular and plural labels, inferring the plural when omitted.
     *
     * @param string $singularLabel
     * @param string $pluralLabel
     * @return static
     */
    final public function label(string $singularLabel, string $pluralLabel = ''): static {
        if ($this->singularLabel === '') {
            $this->labelOverrides = $this->args['labels'] ?? $this->labelOverrides;
        }

        $this->label = $this->singularLabel = $singularLabel;
        $this->pluralLabel = $pluralLabel !== '' ? $pluralLabel : Str::plural($singularLabel);
        return $this->labels($this->labelOverrides);
    }

    /**
     * Sets WordPress label overrides alongside generated defaults.
     *
     * @param array $labels
     * @return static
     */
    final public function labels(array $labels): static {
        $this->labelOverrides = $labels;
        if ($this->singularLabel === '') {
            $this->args['labels'] = $labels;
            return $this;
        }

        $this->args['labels'] = array_merge([
            'name' => $this->pluralLabel,
            'singular_name' => $this->singularLabel,
            'search_items' => 'Search ' . $this->pluralLabel,
            'all_items' => 'All ' . $this->pluralLabel,
            'parent_item' => 'Parent ' . $this->singularLabel,
            'parent_item_colon' => 'Parent ' . $this->singularLabel . ':',
            'edit_item' => 'Edit ' . $this->singularLabel,
            'view_item' => 'View ' . $this->singularLabel,
            'update_item' => 'Update ' . $this->singularLabel,
            'add_new_item' => 'Add New ' . $this->singularLabel,
            'new_item_name' => 'New ' . $this->singularLabel . ' Name',
            'not_found' => 'No ' . $this->pluralLabel . ' found',
            'menu_name' => $this->pluralLabel,
        ], $labels);
        return $this;
    }

    /**
     * Merges WordPress registration arguments, including options without a fluent setter.
     *
     * @param array $args
     * @return static
     */
    final public function args(array $args): static {
        $this->args = array_merge($this->args, $args);
        if (isset($args['labels'])) {
            $this->labels($args['labels']);
        }
        return $this;
    }

    /**
     * Sets the public visibility of the taxonomy.
     *
     * @param bool $isPublic
     * @return static
     */
    final public function public(bool $isPublic = true): static {
        $this->args['public'] = $isPublic;
        return $this;
    }

    /**
     * Sets the private visibility of the taxonomy.
     *
     * @param bool $isPrivate
     * @return static
     */
    final public function private(bool $isPrivate = true): static {
        return $this->public(!$isPrivate);
    }

    /**
     * Sets whether the taxonomy is hierarchical, like categories, or flat, like tags.
     *
     * @param bool $isHierarchical
     * @return static
     */
    final public function hierarchical(bool $isHierarchical = true): static {
        $this->args['hierarchical'] = $isHierarchical;
        return $this;
    }

    /**
     * Sets whether the taxonomy is publicly queryable.
     *
     * @param bool $isQueryable
     * @return static
     */
    final public function queryable(bool $isQueryable = true): static {
        $this->args['publicly_queryable'] = $isQueryable;
        return $this;
    }

    /**
     * Sets whether the taxonomy is shown in the admin UI.
     *
     * @param bool $showUi
     * @return static
     */
    final public function showUi(bool $showUi = true): static {
        $this->args['show_ui'] = $showUi;
        return $this;
    }

    /**
     * Sets whether the taxonomy is shown in the admin menu.
     *
     * @param bool $showInMenu
     * @return static
     */
    final public function showInMenu(bool $showInMenu = true): static {
        $this->args['show_in_menu'] = $showInMenu;
        return $this;
    }

    /**
     * Sets whether the taxonomy is available in navigation menus.
     *
     * @param bool $showInNavMenus
     * @return static
     */
    final public function showInNavMenus(bool $showInNavMenus = true): static {
        $this->args['show_in_nav_menus'] = $showInNavMenus;
        return $this;
    }

    /**
     * Sets whether an associated post type's list table shows a taxonomy column.
     *
     * @param bool $showAdminColumn
     * @return static
     */
    final public function showAdminColumn(bool $showAdminColumn = true): static {
        $this->args['show_admin_column'] = $showAdminColumn;
        return $this;
    }

    /**
     * Sets REST API visibility, optionally configuring the base and controller class.
     *
     * @param bool|array $showInRest
     * @return static
     */
    final public function showInRest(bool|array $showInRest = true): static {
        if (is_array($showInRest)) {
            $this->args['show_in_rest'] = true;
            $this->args['rest_base'] = $showInRest['rest_base'] ?? $this->handle;
            $this->args['rest_controller_class'] = $showInRest['rest_controller_class'] ?? 'WP_REST_Terms_Controller';
        } else {
            $this->args['show_in_rest'] = $showInRest;
        }

        return $this;
    }

    /**
     * Sets the REST API base route.
     *
     * @param string $restBase
     * @return static
     */
    final public function restBase(string $restBase): static {
        $this->args['rest_base'] = $restBase;
        return $this;
    }

    /**
     * Sets the REST API namespace.
     *
     * @param string $restNamespace
     * @return static
     */
    final public function restNamespace(string $restNamespace): static {
        $this->args['rest_namespace'] = $restNamespace;
        return $this;
    }

    /**
     * Sets rewrite rules or disables URL rewriting.
     *
     * @param array|bool $rewrite
     * @return static
     */
    final public function rewrite(array|bool $rewrite): static {
        $this->args['rewrite'] = $rewrite;
        return $this;
    }

    /**
     * Sets the query variable or enables/disables it using the taxonomy handle.
     *
     * @param string|bool $queryVar
     * @return static
     */
    final public function queryVar(string|bool $queryVar = true): static {
        $this->args['query_var'] = $queryVar;
        return $this;
    }

    /**
     * Sets custom taxonomy capabilities.
     *
     * @param array $capabilities
     * @return static
     */
    final public function capabilities(array $capabilities): static {
        $this->args['capabilities'] = $capabilities;
        return $this;
    }

    /**
     * Retrieves the taxonomy's handle.
     *
     * @param string $format The identifier format, defaulting to the taxonomy's configured format.
     * @return string
     */
    final public function getHandle(string $format = 'default'): string {
        return $this->getIdentifier($format);
    }

    /**
     * Retrieves the taxonomy's singular label.
     *
     * @return string
     */
    final public function getSingularLabel(): string {
        return $this->singularLabel;
    }

    /**
     * Retrieves the taxonomy's registration arguments.
     *
     * @return array
     */
    final public function getArgs(): array {
        return $this->args;
    }

    /**
     * Retrieves an argument or the supplied default when it is unset.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    final public function getArg(string $key, mixed $default = null): mixed {
        return $this->args[$key] ?? $default;
    }

    /**
     * Retrieves the associated post type handles.
     *
     * @return array<string>
     */
    final public function getPostTypes(): array {
        return $this->postTypes;
    }

    /**
     * Checks whether the taxonomy is marked as core.
     *
     * @return bool
     */
    final public function isCore(): bool {
        return $this->isCore;
    }
}
