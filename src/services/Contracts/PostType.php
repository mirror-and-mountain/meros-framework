<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Facades\Context;
use MM\Meros\Facades\PostMetaDefinitions as PostMetaFacade;

class PostType extends FeatureDefinition {
    /**
     * The unique handle for this post type, used for registration and referencing in the system.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * The singular label for the post type.
     *
     * @var string
     */
    protected string $singularLabel = '';

    /**
     * The plural label for the post type.
     *
     * @var string
     */
    protected string $pluralLabel = '';

    /**
     * Arguments to be passed to the register_post_type function when registering this post type.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * Meta containers associated with this post type.
     *
     * @var array<PostMeta>
     */
    protected array $metaContainers = [];

    /**
     * The name of the current (working) meta container for this post type.
     *
     * @var string
     */
    protected string $currentMetaContainer = '';

    /**
     * Columns to be displayed in the admin list table for this post type
     *
     * @var array
     */
    protected array $columns = [
        'cb'    => '<input type="checkbox" />',
        'title' => 'Title',
        'date'  => 'Date',
    ];

    /**
     * The link to use for editing the post type. If not set, the default edit link will be used.
     *
     * @var string
     */
    protected string $editPostLink = '';

    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        $this->setProps($props);

        if (!empty($this->metaContainers)) {
            $this->instantiatePostMetaContainers();
        }

        $this->queue();
    }

    /**
     * Queues the post type for registration.
     *
     * @return void
     */
    protected function queue(): void {
        $requiredProps = ['handle', 'singularLabel', 'pluralLabel'];

        foreach ($requiredProps as $prop) {
            if (empty($this->$prop)) {
                return;
            }
        }

        if (!$this->queued) {
            add_action('init', function () {
                register_post_type($this->handle, $this->args);

                if (!empty($this->metaContainers)) {
                    foreach($this->metaContainers as $container) {
                        $container->queueFromPostType($this);
                    }
                }
            });

            add_action('admin_init', function() {
                add_filter('manage_' . $this->handle . '_posts_columns', function() {
                    return $this->columns;
                });
            });

            add_action('save_post_' . $this->handle, function($postId) {
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return;
                }

                if (!empty($this->metaContainers)) {
                    foreach($this->metaContainers as $container) {
                        $container->save($postId);
                    }
                }
            });

            $this->queued = true;
        }
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the post type handle.
     *
     * @param string $handle
     *
     * @return self
     */
    public function handle(string $handle): self {
        $this->handle = Str::slug($handle);

        $this->queue();
        return $this;
    }

    /**
     * Alias of handle(). Sets the post type handle.
     *
     * @param string $handle
     *
     * @return self
     */
    public function name(string $handle): self {
        return $this->handle($handle);
    }

    /**
     * Adds a column to the admin list table for this post type.
     * 
     * @param string $slug  The slug for the column, used for referencing in callbacks.
     * @param string $label The label for the column header.
     * @param Closure       $callback
     *
     * @return self
     */
    public function column(string $slug, string $label, Closure $callback): self {
        $this->columns[$slug] = $label;

        add_action(
            'manage_' . $this->handle . '_posts_custom_column', 
            function(string $column, int $postId) use ($slug, $callback) {
                if ($column !== $slug) {
                    return;
                }

                $meta = null;

                if (!empty($this->metaContainers)) {
                    $meta = collect($this->metaContainers)->mapWithKeys(function($container) use ($postId) {
                        return [$container->name => $container->getValue($postId)];
                    })->toArray();
                }

                $callback($column, $postId, $meta);
        }, 10, 2);

        return $this;
    }

    /**
     * Sets a custom edit link for this post type in the admin list table.
     *
     * @param string|Closure $link A string URL or a Closure that returns a URL to be used as the edit link for this post type.
     *
     * @return self
     */
    public function editLink(string|Closure $link): self {
        $callback = null;

        if (is_string($link)) {
            $this->editPostLink = $link;
        }

        else {
            $callback = $link;
        }

        add_filter('get_edit_post_link', function($link, $postId) use ($callback) {
            $postType = get_post_type($postId);

            if ($postType !== $this->handle) {
                return $link;
            }

            if ($callback !== null) {
                $this->editPostLink = $callback($postId);
                return $this->editPostLink;
            }

            return $this->editPostLink;
        }, 10, 2);

        return $this;
    }

    public function fieldGroup(string $label, Closure $callback): self {
        $slug = Str::snake($label);

        if (isset($this->metaContainers[$slug])) {
            $callback($this->metaContainers[$slug]->getFieldGroup());
            return $this;
        }

        $fieldGroup = $this->metaContainer($slug)->getFieldGroup();
        $callback($fieldGroup);
        return $this;
    }

    /**
     * Retrieves the working meta container for this post type, using the 'default' container if
     * $this->currentMetaContainer isn't set via the metaContainer() method. 
     *
     * @param string $key
     *
     * @return PostMeta
     */
    public function meta(string $container = 'default', string $key = ''): PostMeta {
        $this->currentMetaContainer = $container;

        $container = isset($this->metaContainers[$this->currentMetaContainer]) 
            ? $this->metaContainers[$this->currentMetaContainer] 
            : $this->metaContainer($this->currentMetaContainer);

        if (!empty($key)) {
            return collect($container->getSubItems())->firstWhere('name', $key);
        }

        else {
            return $container;
        }
    }

    /**
     * Sets the working meta container for this post type, using the 'default' container if $key isn't specified.
     *
     * @param string $key
     *
     * @return PostMeta
     */
    public function metaContainer(string $key = ''): PostMeta {
        if (empty($key) || $key === 'default') {
            $container = $this->getDefaultMetaContainer();
            $this->currentMetaContainer = 'default';
            return $container;
        }

        else if (!empty($key) && isset($this->metaContainers[$key])) {
            $this->currentMetaContainer = $key;
            return $this->metaContainers[$key];
        }

        else {
            // Create new meta container with provided name
            $container = PostMetaFacade::checkout($this->provider)->make([
                'name'      => '_' . Str::snake($key),
                'post_type' => $this->handle,
                'type'      => 'object',
                'autoQueue' => false, // Prevent automatic queuing as we'll handle registration through the post type
            ]);

            $this->metaContainers[$key] = $container;
            $this->currentMetaContainer = $key;

            return $container;
        }
    }

    /**
     * Retrieves the default meta container for this post type, creating it if it doesn't already exist.
     *
     * @return PostMeta
     */
    protected function getDefaultMetaContainer(): PostMeta {
        if (isset($this->metaContainers['default'])) {
            return $this->metaContainers['default'];
        }

        $key = '_' . Str::replace('-', '_', $this->handle) . '_meta';

        $container = PostMetaFacade::checkout($this->provider)->make([
            'name'      => $key,
            'post_type' => $this->handle,
            'type'      => 'object',
            'autoQueue' => false, // Prevent automatic queuing as we'll handle registration through the post type
        ]);

        $this->metaContainers['default'] = $container;
        $this->currentMetaContainer = 'default';
        return $container;
    }

    public function metabox(string|array $args, Closure $callback): self {
        $label = is_string($args) ? $args : ($args['label'] ?? 'Meta Box');

        add_action('add_meta_boxes', function () use ($label, $args, $callback) {
            add_meta_box(
                Str::snake($label), 
                $label, 
                function($post) use ($callback) {
                    $meta = null;

                    if (!empty($this->metaContainers)) {
                        $meta = collect($this->metaContainers)->mapWithKeys(function($container) use ($post) {
                            return [$container->name => $container->getValue($post->ID)];
                        })->toArray();
                    }

                    $callback($post, $meta);
                }, 
                $this->handle,
                $args['context'] ?? 'advanced',
                $args['priority'] ?? 'default'
            );
        });
        return $this;
    }

    /**
     * Sets the singular and plural labels for the post type.
     *
     * @param string      $singularLabel The singular label for the post type.
     * @param string|null $pluralLabel   The plural label for the post type. If null, it will be generated by pluralizing the singular label.
     *
     * @return self
     */
    public function label(string $singularLabel, ?string $pluralLabel = null): self {
        $this->singularLabel = $singularLabel;
        $this->pluralLabel   = $pluralLabel ?? Str::plural($singularLabel);

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
     * @return self
     */
    public function labels(array $labels): self {
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

        $this->queue();
        return $this;
    }

    /**
     * Sets the description for the post type.
     *
     * @param string $description
     *
     * @return self
     */
    public function description(string $description): self {
        $this->args['description'] = $description;

        $this->queue();
        return $this;
    }

    /**
     * Sets the post type to be publicly queryable.
     *
     * @return self
     */
    public function public(): self {
        $this->args['public'] = true;

        $this->queue();
        return $this;
    }

    /**
     * Sets the post type to be not publicly queryable.
     *
     * @return self
     */
    public function private(): self {
        $this->args['public'] = false;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type is hierarchical (like pages) or flat (like posts).
     *
     * @param bool $hierarchical
     *
     * @return self
     */
    public function hierarchical(bool $hierarchical = true): self {
        $this->args['hierarchical'] = $hierarchical;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be excluded from search results.
     *
     * @param bool $searchable
     *
     * @return self
     */
    public function searchable(bool $searchable = true): self {
        $this->args['exclude_from_search'] = !$searchable;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be publicly queryable.
     *
     * @param bool $queryable
     *
     * @return self
     */
    public function queryable(bool $queryable = true): self {
        $this->args['publicly_queryable'] = $queryable;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be shown in the admin UI.
     *
     * @param bool $showUi
     *
     * @return self
     */
    public function showUi(bool $showUi = true): self {
        $this->args['show_ui'] = $showUi;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be shown in the admin menu.
     *
     * @param bool $showInMenu
     *
     * @return self
     */
    public function showInMenu(bool $showInMenu = true): self {
        $this->args['show_in_menu'] = $showInMenu;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be shown in navigation menus.
     *
     * @param bool $showInNavMenus
     *
     * @return self
     */
    public function showInNavMenus(bool $showInNavMenus = true): self {
        $this->args['show_in_nav_menus'] = $showInNavMenus;

        $this->queue();
        return $this;
    }

    /**
     * Sets the menu position for the post type in the admin menu.
     *
     * @param int $position The position in the menu order the post type should appear. 
     *                      5 - below Posts, 10 - below Media, 15 - below Links, 20 - below Pages, 25 - below Comments, 60 - below first separator, 65 - below Plugins, 70 - below Users, 75 - below Tools, 80 - below Settings, 100 - below second separator.
     *
     * @return self
     */
    public function menuPosition(int $position): self {
        $this->args['menu_position'] = $position;

        $this->queue();
        return $this;
    }

    /**
     * Sets the menu icon for the post type in the admin menu.
     *
     * @param string $icon The URL to the icon or a Dashicons class name (e.g. 'dashicons-admin-post').
     *
     * @return self
     */
    public function menuIcon(string $icon): self {
        $this->args['menu_icon'] = $icon;

        $this->queue();
        return $this;
    }

    /**
     * Alias of menuIcon(). Sets the menu icon for the post type in the admin menu.
     *
     * @param string $icon The URL to the icon or a Dashicons class name (e.g. 'dashicons-admin-post').
     *
     * @return self
     */
    public function icon(string $icon): self {
        return $this->menuIcon($icon);
    }

    /**
     * Sets whether the post type should be shown in the admin bar.
     *
     * @param bool $showInAdminBar
     *
     * @return self
     */
    public function showInAdminBar(bool $showInAdminBar = true): self {
        $this->args['show_in_admin_bar'] = $showInAdminBar;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be available in the REST API.
     *
     * @param bool $showInRest
     *
     * @return self
     */
    public function showInRest(bool|array $showInRest = true): self {
        if (is_array($showInRest)) {
            $this->args['show_in_rest']   = true;
            $this->args['rest_base']      = $showInRest['rest_base'] ?? null;
            $this->args['rest_namespace'] = $showInRest['rest_namespace'] ?? null;

            return $this;
        }

        $this->args['show_in_rest'] = $showInRest;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should use the block editor (Gutenberg) or the classic editor.
     *
     * @param bool $useBlocks
     *
     * @return self
     */
    public function useBlocks(bool $useBlocks = true): self {
        $this->args['show_in_rest'] = $useBlocks;
        $this->args['supports']     = $useBlocks 
            ? array_merge($this->args['supports'] ?? [], ['editor']) 
            : ($this->args['supports'] ?? []);

        $this->queue();
        return $this;
    }

    /**
     * Sets the REST API base route for the post type.
     *
     * @param string $restBase
     *
     * @return self
     */
    public function restBase(string $restBase): self {
        $this->args['rest_base'] = $restBase;

        $this->queue();
        return $this;
    }

    /**
     * Sets the REST API namespace for the post type.
     *
     * @param string $restNamespace
     *
     * @return self
     */
    public function restNamespace(string $restNamespace): self {
        $this->args['rest_namespace'] = $restNamespace;

        $this->queue();
        return $this;
    }

    /**
     * Sets the capability type for the post type, which determines the base capabilities used for permission checks.
     *
     * @param string|array $capabilityType A string or an array of strings representing the capability type(s) for the post type. 
     *                                     If a string is provided, it will be used for both singular and plural capability types. 
     *                                     If an array is provided, it should have 'singular' and 'plural' keys for respective capability types.
     *
     * @return self
     */
    public function capabilityType(string|array $capabilityType): self {
        $this->args['capability_type'] = $capabilityType;

        $this->queue();
        return $this;
    }

    /**
     * Sets custom capabilities for the post type, allowing for fine-grained control over permissions.
     *
     * @param array $capabilities An associative array of capability keys and their corresponding values. 
     *                            This allows you to define custom capabilities for actions like editing, deleting, and publishing posts of this type.
     *
     * @return self
     */
     public function capabilities(array $capabilities): self {
        $this->args['capabilities'] = $capabilities;

        $this->queue();
        return $this;
    }

    /**
    * Sets whether the post type should use the default capabilities or custom capabilities.
    *
    * @param bool $mapMetaCaps If true, the post type will use the default meta capabilities. If false, it will rely on custom capabilities defined in the 'capabilities' argument.
    *
    * @return self
    */
    public function mapMetaCapabilities(bool $mapMetaCaps = true): self {
        $this->args['map_meta_cap'] = $mapMetaCaps;

        $this->queue();
        return $this;
    }

    /**
    * Sets the supports array for the post type, which determines the features that are enabled for this post type (e.g. editor, thumbnail, custom-fields).
    *
    * @param array $supports An array of features that this post type supports. 
    *                        Common values include 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', etc.
    *
    * @return self
    */
    public function supports(array $supports): self {
        $this->args['supports'] = $supports;

        $this->queue();
        return $this;
    }

    /**
    * Sets the taxonomies that are associated with this post type.
    *
    * @param array $taxonomies An array of taxonomy names (strings) that should be associated with this post type. 
    *                          This allows you to link existing taxonomies to the post type or create new ones.
    *
    * @return self
    */
    public function taxonomies(array $taxonomies): self {
        $this->args['taxonomies'] = $taxonomies;

        $this->queue();
        return $this;
    }

    /**
    * Sets the rewrite rules for the post type's permalinks.
    *
    * @param array|bool $rewrite An associative array of rewrite rules or false to disable rewrites for this post type. 
    *                            The array can include keys like 'slug', 'with_front', 'pages', and 'feeds' to customize permalink structure.
    *
    * @return self
    */
    public function rewrite(array|bool $rewrite): self {
        $this->args['rewrite'] = $rewrite;

        $this->queue();
        return $this;
    }

    /**
    * Sets whether the post type should have an archive page.
    *
    * @param bool|string $hasArchive A boolean indicating whether the post type should have an archive page, or a string to specify a custom archive slug.
    * 
    * @return self
    */
    public function hasArchive(bool|string $hasArchive = true): self {
        $this->args['has_archive'] = $hasArchive;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be available for export in the WordPress export tool.
     *
     * @param bool $exportable
     *
     * @return self
     */
    public function exportable(bool $exportable = true): self {
        $this->args['can_export'] = $exportable;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post type should be deleted when its associated user is deleted.
     *
     * @param bool $deleteWithUser
     *
     * @return self
     */
    public function deleteWithUser(bool $deleteWithUser = true): self {
        $this->args['delete_with_user'] = $deleteWithUser;

        $this->queue();
        return $this;
    }

    /**
     * Sets a custom template for the post type, which can be used to define a default block structure when creating new posts of this type.
     *
     * @param array $template An array of block definitions that represent the default content structure for new posts of this type. 
     *                        Each block definition should include a 'blockName' key for the block type and an optional 'attrs' key for block attributes.
     *
     * @return self
     */
    public function template(array $template): self {
        $this->args['template'] = $template;

        $this->queue();
        return $this;
    }

    /**
     * Sets the template lock mode for the post type, which controls how the block template can be modified by users.
     *
     * @param string|bool $templateLock A string indicating the template lock mode ('all', 'insert', 'remove') or a boolean (true for 'all', false for no lock).
     *
     * @return self
     */
    public function templateLock(string|bool $templateLock): self {
        $this->args['template_lock'] = $templateLock;

        $this->queue();
        return $this;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Retrieves the post type's arguments.
     *
     * @return array
     */
    public function getArgs(): array {
        return $this->args;
    }


    /***************************
     * Helpers
     ***************************/

    /**
     * Instantiates post meta containers from their class names if provided.
     *
     * @return void
     */
    protected function instantiatePostMetaContainers(): void {
        if (empty($this->metaContainers)) {
            return;
        }

        foreach ($this->metaContainers as $index => $meta) {
            if (is_string($meta) && !empty($meta)) {
                $this->metaContainers[$index] = PostMetaFacade::checkout($this->provider)->makeFrom($meta);
            }
        }
    }
}