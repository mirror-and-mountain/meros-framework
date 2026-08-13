<?php

namespace MM\Meros\Contracts\Features\Content;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Components\FieldGroup;

use MM\Meros\Contracts\Features\Makeable;
use MM\Meros\Contracts\Features\Registrable;

use MM\Meros\Contracts\Features\Data\PostMetaContainer;

use MM\Meros\Contracts\Features\Concerns\IsMakeable;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsHookable;

use MM\Meros\Contracts\Features\Concerns\InstantiatesItems;
use MM\Meros\Contracts\Features\Concerns\MakesItems;

class PostType extends Feature implements Makeable, Registrable {
    /**
     * The post type's handle (name).
     *
     * @var string
     */
    protected string $handle = '';

    /**
     * The post type's singular label.
     *
     * @var string
     */
    protected string $singularLabel = '';
    
    /**
     * The post type's plural label.
     *
     * @var string
     */
    protected string $pluralLabel = '';

    /**
     * An array of arguments for registering the post type.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * An array of associated post meta containers for this post type.
     *
     * @var array<PostMetaContainer|array>
     */
    protected array $metaContainers = [];

    /**
     * Whether the post type is a core post type (like 'post' or 'page').
     *
     * @var boolean
     */
    protected bool $isCore = false;

    use IsRegistrable, 
        IsMakeable, 
        IsHookable,
        InstantiatesItems,
        MakesItems;

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        $this->setHook('init', [$this, 'register']);
        $this->hook();
    }

    /**
     * Instantiates and configures the post meta containers associated with this post type, if defined as arrays of properties.
     *
     * @return void
     */
    final protected function whenConfigured(): void {
        if (!empty($this->metaContainers) && !($this->metaContainers[0] instanceof PostMetaContainer)) {
            foreach ($this->metaContainers as $index => $properties) {
                if (is_string($properties) && Str::contains($properties, '\\')) {
                    $class = $properties;
                    $this->metaContainers[$index] = $this->makeItemFrom($class, PostMetaContainer::class);
                }

                else if (is_array($properties) && !empty($properties['name']) ?? '') {
                    $name = $properties['name'];
                    $properties = $properties['properties'] ?? [];

                    if ($this->itemIsRegistered($name, PostMetaContainer::class)) {
                        $this->metaContainers[$index] = $this->makeItemFrom(
                            $name,
                            PostMetaContainer::class,
                            $properties
                        );
                    }

                    else {
                        $this->metaContainers[$index] = $this->makeItem(
                            PostMetaContainer::class,
                            $properties
                        );
                    }

                    if ($this->metaContainers[$index] instanceof PostMetaContainer) {
                        $this->metaContainers[$index]->postType($this->handle);
                    }
                }
            }
        }
    }

    /**
     * Marks the post type as a core post type, which prevents it from being registered by the framework.
     *
     * @param boolean $isCore
     *
     * @return static
     */
    final public function core(bool $isCore = true): static {
        $this->isCore = $isCore;
        return $this;
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    final public function register(): void {
        if ($this->isCore || empty($this->handle)) {
            return;
        }

        if (in_array($this->handle, get_post_types())) {
            return;
        }

        register_post_type($this->handle, $this->args);
    }

    // =========================================================================
    // Post Meta Association
    // =========================================================================

    /**
     * Associates a post meta container with this post type.
     *
     * @param PostMetaContainer|array $callbackOrProps The post meta container instance or an array of properties to create one.
     *
     * @return PostMetaContainer The associated post meta container instance.
     */
    final public function meta(
        PostMetaContainer|Closure|string $containerOrClosure, 
        Closure|array                    $callbackOrProps = []
    ): PostMetaContainer {
        if (is_string($containerOrClosure)) {
            $classOrAlias = $containerOrClosure;

            // Check if the container has been registered with the PostMetaContainers register
            // If registered, set up the instance, or make a new one if not registered
            $container = $this->itemIsRegistered($classOrAlias, PostMetaContainer::class) 
                ? $this->makeItemFrom(
                    $classOrAlias,
                    PostMetaContainer::class,
                    $callbackOrProps,
                )
                : $this->makeItem(
                    PostMetaContainer::class,
                    $callbackOrProps,
                    ['name' => $classOrAlias]
                );

            if ($container instanceof PostMetaContainer) {
                $container->postType($this->handle);
                $this->metaContainers[] = $container;
            }
        }
    
        // Create a new container instance with the provided closure
        else if ($containerOrClosure instanceof Closure) {
            $closure = $containerOrClosure;
            $container = $this->makeItem(PostMetaContainer::class, $closure);

            if ($container instanceof PostMetaContainer) {
                $container->postType($this->handle);
                $this->metaContainers[] = $container;
            }
        }

        else {
            $container = $containerOrClosure;
            $container->postType($this->handle);
            $this->metaContainers[] = $container;
        }

        return $container;
    }

    /**
     * Associates a field group with this post type, converting it into a post meta container and adding it to the post type's meta containers.
     *
     * @param FieldGroup|Closure|string $fieldGroupOrClosure
     * @param array                     $callbackOrProps
     *
     * @return PostMetaContainer
     */
    final public function fields(FieldGroup|Closure|string $fieldGroupOrClosure, Closure|array $callbackOrProps = []): PostMetaContainer {
        $fieldGroup = null;

        // See if a container has already been created for this field group.
        if (is_string($fieldGroupOrClosure)) {
            $classOrAlias = $fieldGroupOrClosure;

            $alias = Str::contains($classOrAlias, '\\') 
                ? Str::slug(class_basename($classOrAlias)) 
                : $classOrAlias;

            $containerName = Str::replace('-', '_', $alias);
            $container = $this->getItem($containerName, PostMetaContainer::class);

            // Add this post type to the existing container and return it
            if ($container !== null && $container instanceof PostMetaContainer) {
                $container->postType($this->handle);
                $this->metaContainers[] = $container;
                return $container;
            }

            // If no existing container was found, setup the field group
            $fieldGroup = $this->getItem($classOrAlias, FieldGroup::class) ??
                $this->makeItemFrom(
                    $classOrAlias,
                    FieldGroup::class,
                    $callbackOrProps
                );
        }

        // Create a new field group from the provided closure
        else if ($fieldGroupOrClosure instanceof Closure) {
            $closure = $fieldGroupOrClosure;
            $fieldGroup = $this->makeItem(FieldGroup::class, $closure);
        }

        else {
            $fieldGroup = $fieldGroupOrClosure;
        }

        if ($fieldGroup === null || !($fieldGroup instanceof FieldGroup)) {
            throw new \InvalidArgumentException('The provided field group is not a valid FieldGroup instance or could not be resolved.');
        }

        // Convert the field group into a post meta container and associate it with this post type
        $container = $this->convertFieldGroupToMeta($fieldGroup);

        if ($container instanceof PostMetaContainer) {
            $this->metaContainers[] = $container;
        } else {
            throw new \RuntimeException('Failed to convert the provided FieldGroup into a PostMetaContainer.');
        }

        return $container;
    }

    /**
     * Converts a FieldGroup instance into a PostMetaContainer and associates it with this post type.
     *
     * @param FieldGroup $fieldGroup The FieldGroup instance to convert.
     *
     * @return PostMetaContainer|null The converted PostMetaContainer, or null if conversion failed.
     */
    private function convertFieldGroupToMeta(FieldGroup $fieldGroup): ?PostMetaContainer {
        $id     = $fieldGroup->getId();
        $title  = $fieldGroup->getTitle();
        $fields = $fieldGroup->getFields();

        if (empty($fields)) {
            return null;
        }

        $container = $this->makeItem(
            PostMetaContainer::class,
            [
                'name'        => Str::replace('-', '_', $id),
                'label'       => $title,
                'description' => $fieldGroup->getDescription()
            ]
        );

        if (!($container instanceof PostMetaContainer)) {
            return null;
        }

        $container->postType($this->handle);
        $container->fieldGroup($id);

        foreach ($fields as $field) {
            $type    = $field->getDataType();
            $name    = $field->getName();
            $default = $field->getDefaultValue();
            $description = $field->getDescription();

            $container->add(function ($item) use ($type, $name, $default, $description, $field) {
                $item->{$type}($name);
                $item->description($description);

                if ($default !== null) {
                    $item->default($default);
                }

                $item->__addExistingField($field);
            });   
        }

        return $container;
    }
    

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    final public function setIdentifier(string $handle): static {
        return $this->handle($handle);
    }

    /**
     * Sets the post type's handle.
     *
     * @param string $handle The handle to set.
     * @return static
     */
    final public function handle(string $handle): static {
        $this->handle = Str::slug($handle);
        return $this;
    }

    /**
     * Sets the post type's handle (alias for handle method).
     *
     * @param string $name The name to set as the handle.
     * @return static
     */
    final public function name(string $name): static {
        return $this->handle($name);
    }

    /**
     * Sets the singular and plural labels for the post type.
     *
     * @param string $singularLabel The singular label for the post type.
     * @param string $pluralLabel   The plural label for the post type. If empty, it will be generated by pluralizing the singular label.
     *
     * @return static
     */
    final public function label(string $singularLabel, string $pluralLabel = ''): static {
        $this->singularLabel = $singularLabel;
        $this->pluralLabel   = $pluralLabel !== '' ? $pluralLabel : Str::plural($singularLabel);

        if (!isset($this->args['labels'])) {
            $this->labels([]); // Initialise default labels if they haven't been set yet
        }

        return $this;
    }

    /**
     * Sets the labels for the post type using an array of label overrides.
     *
     * @param array $labels An associative array of label keys and their corresponding values. 
     *                      Any labels not provided will be generated based on the singular and plural labels.
     * 
     * @return static
     */
    final public function labels(array $labels): static {
        $parsedLabels = [
            'name'                      => $labels['name'] ?? $this->pluralLabel,
            'singular_name'             => $labels['singular_name'] ?? $this->singularLabel,
            'add_new'                   => $labels['add_new'] ?? 'Add ' . $this->singularLabel,
            'add_new_item'              => $labels['add_new_item'] ?? 'Add New ' . $this->singularLabel,
            'edit_item'                 => $labels['edit_item'] ?? 'Edit ' . $this->singularLabel,
            'new_item'                  => $labels['new_item'] ?? 'New ' . $this->singularLabel,
            'view_item'                 => $labels['view_item'] ?? 'View ' . $this->singularLabel,
            'view_items'                => $labels['view_items'] ?? 'View ' . $this->pluralLabel,
            'search_items'              => $labels['search_items'] ?? 'Search ' . $this->pluralLabel,
            'not_found'                 => $labels['not_found'] ?? 'No ' . $this->pluralLabel . ' found',
            'not_found_in_trash'        => $labels['not_found_in_trash'] ?? 'No ' . $this->pluralLabel . ' found in Trash',
            'parent_item_colon'         => $labels['parent_item_colon'] ?? 'Parent ' . $this->singularLabel . ':',
            'all_items'                 => $labels['all_items'] ?? 'All ' . $this->pluralLabel,
            'archives'                  => $labels['archives'] ?? $this->singularLabel . ' Archives',
            'attributes'                => $labels['attributes'] ?? $this->singularLabel . ' Attributes',
            'insert_into_item'          => $labels['insert_into_item'] ?? 'Insert into ' . $this->singularLabel,
            'uploaded_to_this_item'     => $labels['uploaded_to_this_item'] ?? 'Uploaded to this ' . $this->singularLabel,
            'featured_image'            => $labels['featured_image'] ?? 'Featured Image',
            'set_featured_image'        => $labels['set_featured_image'] ?? 'Set featured image',
            'remove_featured_image'     => $labels['remove_featured_image'] ?? 'Remove featured image',
            'use_featured_image'        => $labels['use_featured_image'] ?? 'Use as featured image',
            'menu_name'                 => $labels['menu_name'] ?? $this->pluralLabel,
            'filter_items_list'         => $labels['filter_items_list'] ?? 'Filter ' . $this->pluralLabel . ' list',
            'items_list_navigation'     => $labels['items_list_navigation'] ?? $this->pluralLabel . ' list navigation',
            'items_list'                => $labels['items_list'] ?? $this->pluralLabel . ' list',
            'item_published'            => $labels['item_published'] ?? $this->singularLabel . ' published',
            'item_published_privately'  => $labels['item_published_privately'] ?? $this->singularLabel . ' published privately',
            'item_reverted_to_draft'    => $labels['item_reverted_to_draft'] ?? $this->singularLabel . ' reverted to draft',
            'item_scheduled'            => $labels['item_scheduled'] ?? $this->singularLabel . ' scheduled',
            'item_updated'              => $labels['item_updated'] ?? $this->singularLabel . ' updated',
            'item_trashed'              => $labels['item_trashed'] ?? $this->singularLabel . ' trashed',
            'item_link'                 => $labels['item_link'] ?? 'View ' . $this->singularLabel . ' link',
            'item_link_description'     => $labels['item_link_description'] ?? 'A link to a ' . $this->singularLabel,
        ];

        $this->args['labels'] = $parsedLabels;
        return $this;
    }

    /**
     * Sets the description for the post type.
     *
     * @param string $description The description to set.
     * @return static
     */
    final public function description(string $description): static {
        $this->args['description'] = $description;
        return $this;
    }

    /**
     * Sets the public visibility of the post type.
     *
     * @param bool $isPublic Whether the post type should be publicly visible. Default is true.
     * @return static
     */
    final public function public(bool $isPublic = true): static {
        $this->args['public'] = $isPublic;
        return $this;
    }

    /**
     * Sets the private visibility of the post type.
     *
     * @param bool $isPrivate Whether the post type should be private. Default is true.
     * @return static
     */
    final public function private(bool $isPrivate = true): static {
        $this->args['public'] = !$isPrivate;
        return $this;
    }

    /**
     * Sets whether the post type is hierarchical (like pages) or flat (like posts).
     *
     * @param bool $isHierarchical Whether the post type should be hierarchical. Default is true.
     * @return static
     */
    final public function hierarchical(bool $isHierarchical = true): static {
        $this->args['hierarchical'] = $isHierarchical;
        return $this;
    }

    /**
     * Sets whether the post type should be included in search results.
     *
     * @param bool $isSearchable Whether the post type should be searchable. Default is true.
     * @return static
     */
    final public function searchable(bool $isSearchable = true): static {
        $this->args['exclude_from_search'] = !$isSearchable;
        return $this;
    }

    /**
     * Sets whether the post type should be publicly queryable.
     *
     * @param bool $isQueryable Whether the post type should be publicly queryable. Default is true.
     * @return static
     */
    final public function queryable(bool $isQueryable = true): static {
        $this->args['publicly_queryable'] = $isQueryable;
        return $this;
    }

    /**
     * Sets whether the post type should be shown in the admin UI.
     *
     * @param bool $showUi Whether the post type should be shown in the admin UI. Default is true.
     * @return static
     */
    final public function showUi(bool $showUi = true): static {
        $this->args['show_ui'] = $showUi;
        return $this;
    }

    /**
     * Sets whether the post type should be shown in the admin menu.
     *
     * @param bool $showInMenu Whether the post type should be shown in the admin menu. Default is true.
     * @return static
     */
    final public function showInMenu(bool $showInMenu = true): static {
        $this->args['show_in_menu'] = $showInMenu;
        return $this;
    }

    /**
     * Sets whether the post type should be shown in navigation menus.
     *
     * @param bool $showInNavMenus Whether the post type should be shown in navigation menus. Default is true.
     * @return static
     */
    final public function showInNavMenus(bool $showInNavMenus = true): static {
        $this->args['show_in_nav_menus'] = $showInNavMenus;
        return $this;
    }

    /**
     * Sets the position of the post type in the admin menu.
     *
     * @param int $position The position in the menu order. Default is 5.
     * @return static
     */
    final public function menuPosition(int $position): static {
        $this->args['menu_position'] = $position;
        return $this;
    }

    /**
     * Sets the icon for the post type in the admin menu.
     *
     * @param string $icon The URL or dashicon class for the menu icon.
     * @return static
     */
    final public function menuIcon(string $icon): static {
        $this->args['menu_icon'] = $icon;
        return $this;
    }

    /**
     * Sets whether the post type should be shown in the admin bar.
     *
     * @param bool $showInAdminBar Whether the post type should be shown in the admin bar. Default is true.
     * @return static
     */
    final public function showInAdminBar(bool $showInAdminBar = true): static {
        $this->args['show_in_admin_bar'] = $showInAdminBar;
        return $this;
    }

    /**
     * Sets whether the post type should be included in the REST API.
     *
     * @param bool|array $showInRest Whether the post type should be included in the REST API. Default is true.
     *                               If an array is provided, it can contain 'rest_base' and 'rest_controller_class' keys.
     * @return static
     */
    final public function showInRest(bool|array $showInRest = true): static {
        if (is_array($showInRest)) {
            $this->args['show_in_rest'] = true;
            $this->args['rest_base'] = $showInRest['rest_base'] ?? $this->handle;
            $this->args['rest_controller_class'] = $showInRest['rest_controller_class'] ?? 'WP_REST_Posts_Controller';
        } else {
            $this->args['show_in_rest'] = $showInRest;
        }

        return $this;
    }

    /**
     * Sets whether the post type should use the block editor (Gutenberg).
     *
     * @param bool $useBlocks Whether to use the block editor. Default is true.
     *
     * @return static
     */
    final public function useBlocks(bool $useBlocks = true): static {
        $this->args['show_in_rest'] = $useBlocks;
        $this->args['supports']     = $useBlocks 
            ? array_merge($this->args['supports'] ?? [], ['editor']) 
            : ($this->args['supports'] ?? []);

        return $this;
    }

    /**
     * Sets an array of allowed blocks for the post type when using the block editor.
     *
     * @param array $blocks
     *
     * @return static
     */
    final public function allowedBlocks(array $blocks): static {
        add_filter('allowed_block_types_all', function ($allowedBlocks, $editorContext) use ($blocks) {
            if ($editorContext->post !== null && $editorContext->post->post_type === $this->handle) {
                return $blocks;
            }

            return $allowedBlocks;
        }, 10, 2);

        return $this;
    }

    /**
     * Sets the REST API base route for the post type.
     *
     * @param string $restBase
     *
     * @return static
     */
    final public function restBase(string $restBase): static {
        $this->args['rest_base'] = $restBase;
        return $this;
    }

    /**
     * Sets the REST API namespace for the post type.
     *
     * @param string $restNamespace
     *
     * @return static
     */
    final public function restNamespace(string $restNamespace): static {
        $this->args['rest_namespace'] = $restNamespace;
        return $this;
    }

    /**
     * Sets the capability type for the post type, which determines the base capabilities used for permission checks.
     *
     * @param string|array $capabilityType A string or an array of strings representing the capability type(s) for the post type. 
     *                                     If a string is provided, it will be used for both singular and plural capability types. 
     *                                     If an array is provided, it should have 'singular' and 'plural' keys for respective capability types.
     *
     * @return static
     */
    final public function capabilityType(string|array $capabilityType): static {
        $this->args['capability_type'] = $capabilityType;
        return $this;
    }

    /**
     * Sets custom capabilities for the post type, allowing for fine-grained control over permissions.
     *
     * @param array $capabilities An associative array of capability keys and their corresponding values. 
     *                            This allows you to define custom capabilities for actions like editing, deleting, and publishing posts of this type.
     *
     * @return static
     */
    final public function capabilities(array $capabilities): static {
        $this->args['capabilities'] = $capabilities;
        return $this;
    }

    /**
     * Sets whether the post type should map meta capabilities to primitive capabilities.
     *
     * @param bool $mapMetaCaps Whether to map meta capabilities. Default is true.
     *
     * @return static
     */
    final public function mapMetaCapabilities(bool $mapMetaCaps = true): static {
        $this->args['map_meta_cap'] = $mapMetaCaps;
        return $this;
    }

    /**
     * Sets the features that the post type supports, such as title, editor, thumbnail, etc.
     *
     * @param array $supports An array of features that the post type should support. 
     *                        This can include 'title', 'editor', 'thumbnail', 'excerpt', 'comments', and more.
     *
     * @return static
     */
    final public function supports(array $supports): static {
        $this->args['supports'] = $supports;
        return $this;
    }

    /**
     * Sets the taxonomies associated with the post type, allowing for categorization and tagging.
     *
     * @param array $taxonomies
     *
     * @return static
     */
    final public function taxonomies(array $taxonomies): static {
        $this->args['taxonomies'] = $taxonomies;
        return $this;
    }

    /**
     * Sets the rewrite rules for the post type, allowing for custom URL structures.
     *
     * @param array|boolean $rewrite
     *
     * @return static
     */
    final public function rewrite(array|bool $rewrite): static {
        $this->args['rewrite'] = $rewrite;
        return $this;
    }

    /**
     * Sets whether the post type should have an archive page, and optionally specifies the archive slug.
     *
     * @param boolean $hasArchive
     *
     * @return static
     */
    final public function hasArchive(bool|string $hasArchive = true): static {
        $this->args['has_archive'] = $hasArchive;
        return $this;
    }

    /**
     * Sets whether the post type should be available for export in the WordPress export tool.
     *
     * @param bool $exportable
     *
     * @return static
     */
    final public function exportable(bool $exportable = true): static {
        $this->args['can_export'] = $exportable;
        return $this;
    }

    /**
     * Sets whether the post type should be deleted when its associated user is deleted.
     *
     * @param bool $deleteWithUser
     *
     * @return static
     */
    final public function deleteWithUser(bool $deleteWithUser = true): static {
        $this->args['delete_with_user'] = $deleteWithUser;
        return $this;
    }

    /**
     * Sets a custom template for the post type, which can be used to define a default block structure when creating new posts of this type.
     *
     * @param array $template An array of block definitions that represent the default content structure for new posts of this type. 
     *                        Each block definition should include a 'blockName' key for the block type and an optional 'attrs' key for block attributes.
     *
     * @return static
     */
    final public function template(array $template): static {
        $this->args['template'] = $template;
        return $this;
    }

    /**
     * Sets the template lock mode for the post type, which controls how the block template can be modified by users.
     *
     * @param string|bool $templateLock A string indicating the template lock mode ('all', 'insert', 'remove') or a boolean (true for 'all', false for no lock).
     *
     * @return static
     */
    final public function templateLock(string|bool $templateLock): static {
        $this->args['template_lock'] = $templateLock;
        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    final public function getIdentifier(): string {
        return $this->getHandle();
    }

    /**
     * Retrieves the post type's handle.
     *
     * @return string
     */
    final public function getHandle(): string {
        return $this->handle;
    }

    /**
     * Retrieves the post type's arguments array.
     *
     * @return array
     */
    final public function getArgs(): array {
        return $this->args;
    }

    /**
     * Retrieves a specific argument value from the post type's arguments array.
     *
     * @param string $key The key of the argument to retrieve.
     * @param mixed  $default The default value to return if the key does not exist.
     *
     * @return mixed
     */
    final public function getArg(string $key, mixed $default = null): mixed {
        return $this->args[$key] ?? $default;
    }

    /**
     * Checks if the post type is marked as a core post type.
     *
     * @return bool
     */
    final public function isCore(): bool {
        return $this->isCore;
    }
}