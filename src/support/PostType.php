<?php 

namespace MM\Meros\App\Support;

use Illuminate\Support\Str;
use MM\Meros\App\Support\Admin\Helpers;

class PostType {
    // Basic information
    public string $handle = '';
    public string $description = '';
    public string $singularName = '';
    public string $pluralName = '';
    public array  $labels = [];

    // Visibility and UI
    public bool $public = false;
    public bool $hierarchical = false;
    
    // Influence of 'public' argument
    public bool $excludeFromSearch;
    public bool $publiclyQueryable;
    public bool $showUi;
    public bool $showInNavMenus;
    public bool $showInAdminBar;
    public bool|string $showInMenu;

    // REST API
    public bool $showInRest; // Influenced by 'public' argument if not explicitly set
    public string $restBase = '';
    public string $restNamespace = 'wp/v2';
    public string $restControllerClass = \WP_REST_Posts_Controller::class;

    // REST API controllers for autosaves and revisions (if show_in_rest is true)
    public string|bool $autoSaveRestControllerClass = \WP_REST_Autosaves_Controller::class;
    public string|bool $revisionsRestControllerClass = \WP_REST_Revisions_Controller::class;
    public bool        $lateRouteRegistration = false;

    // Admin menu
    public ?int    $menuPosition = null;
    public ?string $menuIcon = null;

    // Capabilities
    public string|array $capabilityType = 'post';
    public array        $capabilities = [];
    public bool         $mapMetaCap = true;

    // Supports
    public array|false $supports = ['title', 'editor'];

    // Taxonomies
    public array $taxonomies = [];

    // Archive and URL structure
    public bool $hasArchive = false;

    public bool|string $rewrite = true;
    public bool|string $queryVar = true;

    // Export and deletion
    public bool $canExport = true;
    public bool $deleteWithUser = false;

    // Block editor and template (if enabled)
    public bool         $useBlockEditor = true;
    public array        $template = [];
    public string|false $templateLock = false;

    // Parsed arguments to be passed to register_post_type
    public array $parsedArguments = [];

    // Result of the register_post_type function, stored for reference
    public \WP_Post_Type|\WP_Error $registrationResult;

    // Meta fields and boxes to be registered for this post type
    public array $meta = [];
    public array $metaBoxes = [];

    // Argument keys accepted by the register_post_type function
    protected array $argumentKeys = [
        'labels',
        'description',
        'public',
        'hierarchical',
        'exclude_from_search',
        'publicly_queryable',
        'show_ui',
        'show_in_menu',
        'show_in_nav_menus',
        'show_in_admin_bar',
        'show_in_rest',
        'rest_base',
        'rest_namespace',
        'rest_controller_class',
        'auto_save_rest_controller_class',
        'revisions_rest_controller_class',
        'late_route_registration',
        'menu_position',
        'menu_icon',
        'capability_type',
        'capabilities',
        'map_meta_cap',
        'supports',
        'taxonomies',
        'has_archive',
        'rewrite',
        'query_var',
        'can_export',
        'delete_with_user',
        'use_block_editor',
        'template',
        'template_lock',
    ];

    /**
     * Instantiates the class.
     *
     * @param  string $handle
     * @param  array  $args
     * @param  bool   $requireChild
     */
    protected function __construct(string $handle = '', array $args = [], bool $requireChild = true) {
        $framworkClasses = [PostType::class, StructuredPostType::class];

        if ($requireChild && in_array(static::class, $framworkClasses)) {
            throw new \Exception('When using the registerAsClass method, you must use a child post-type class that extends either the PostType or StructuredPostType class and set the $handle property.');
        }

        if ($requireChild && $this->handle === '') {
            throw new \Exception('When using the registerAsClass method, you must set the $handle property on the child class.');
        }

        if (! $requireChild && ! in_array(static::class, $framworkClasses)) {
            throw new \Exception('When using the register method, you must use either the PostType or StructuredPostType class directly and pass the handle as an argument, rather than using a child class.');
        }

        if ($requireChild === false) {
            $this->handle = Str::slug($handle);
        }

        else {
            // Ensure the handle is slugified
            $this->handle = Str::slug($this->handle);

            // Use the class properties as args if we're using a child class
            foreach ($this->argumentKeys as $key) {
                $camelKey = Str::camel($key);

                if (! property_exists($this, $camelKey) || ! isset($this->$camelKey)) {
                    continue;
                }

                $args[ $key ] = $this->$camelKey;
            }
        }

        if (post_type_exists($this->handle)) {
            throw new \Exception("Post type {$this->handle} already registered.");
        }

        // Parse the arguments
        $this->parseArguments($args, ! $requireChild);
    }

    /**
     * Returns an array of default values for the post type arguments. 
     * Can be overridden by child classes to force specific defaults.
     *
     * @return array
     */
    protected function defaults(): array {
        return [];
    }

    /**
     * Instantiates the post type using the class properties and defaults, 
     * without needing to pass arguments. Can only be used by child classes that set
     * the $handle property
     *
     * @return self
     */
    public static function registerAsClass(): self {
        $instance = new static();
        $instance->register();
        return $instance;
    }

    /**
     * Instantiates the post type and registers it with WordPress
     *
     * @param  string $handle
     * @param  array  $args
     *
     * @return self
     */
    public static function make(string $handle, array $args = []): self {
        $instance = new static($handle, $args, false);
        return $instance;
    }

    /******************************************
     * Chainable methods for building the cpt
     ******************************************/

    public function public(): self {
        $this->public = true;
        $this->parseArguments(['public' => true]);
        return $this;
    }

    public function private(): self {
        $this->public = false;
        $this->parseArguments(['public' => false]);
        return $this;
    }

    public function ui(): self {
        $this->showUi = true;
        $this->parseArguments(['show_ui' => true]);
        return $this;
    }

    public function showInMenu(): self {
        $this->showInMenu = true;
        $this->parseArguments(['show_in_menu' => true]);
        return $this;
    }

    public function showInAdminBar(): self {
        $this->showInAdminBar = true;
        $this->parseArguments(['show_in_admin_bar' => true]);
        return $this;
    }
    
    /**
     * Chainable method to register meta fields alongside the post type.
     *
     * @param  array $meta
     *
     * @return self
     */
    public function withMeta(array $meta): self {
        $this->meta = $this->parseMeta($meta);
        $this->registerMeta();
        return $this;
    }

    /**
     * Chainable method to register custom meta boxes alongside the post type.
     *
     * @param  array $metaBoxes
     *
     * @return self
     */
    public function withMetaBoxes(array $metaBoxes): self {
        $this->metaBoxes = $this->parseMetaBoxes($metaBoxes);
        $this->registerMetaBoxes();
        return $this;
    }

    /**
     * Chainable method to register the default meta box with the post type.
     *
     * @return self
     */
    public function withDefaultMetaBox(): self {
        $this->metaBoxes[] = [
            'id' => $this->handle . '_default_meta_box',
            'callback' => [$this, 'defaultMetaBoxCallback'],
        ];

        $this->registerMetaBoxes();
        return $this;
    }

    /*****************************************************
     * Methods for registering items with WordPress.
     *****************************************************/

    /**
     * Registers the post type with WordPress
     *
     * @return void
     */
    protected function register(): void {
        $instance = $this;

        add_action('init', function () use ($instance) {
            if ($instance->useBlockEditor === false) {
                $instance->disableBlockEditor();
            }

            $instance->registrationResult = \register_post_type(
                $instance->handle,
                $instance->parsedArguments
            );
        });
    }

    /**
     * Registers the meta fields for this post type with WordPress
     *
     * @return void
     */
    protected function registerMeta(): void {
        if ($this->meta === []) {
            return;
        }

        $instance = $this;

        add_action('init', function () use ($instance) {
            foreach ($instance->meta as $metaField) {
                register_post_meta(
                    $instance->handle,
                    $metaField['key'],
                    $metaField['args']
                );
            }
        });
    }

    /**
     * Registers the meta boxes for this post type with WordPress
     *
     * @return void
     */
    protected function registerMetaBoxes(): void {
        if ($this->metaBoxes === []) {
            return;
        }

        add_action('add_meta_boxes_' . $this->handle, function () {
            foreach ($this->metaBoxes as $metaBox) {
                add_meta_box(
                    $metaBox['id'],
                    $metaBox['title'],
                    $metaBox['callback'],
                    $this->handle,
                    $metaBox['context'],
                    $metaBox['priority'],
                    $metaBox['callback_args'],
                );
            }
        });
    }

    /***********************
     * Post Type Parsers
     ***********************/

    /**
     * Parses the arguments passed to the post type and sets the appropriate properties on the class instance
     *
     * @param  array $args
     * @param  bool  $setAllProps Whether to set class properties based on the parsed arguments.
     *
     * @return void
     */
    protected function parseArguments(array $args = [], bool $setAllProps = false): void {
        $args   = array_merge($args, $this->defaults());
        $public = $args['public'] ?? false;

        $args['labels']  = $this->parseLabels($args['labels'] ?? []);
        $args['public']  = $public;

        // Set public influenced arguments if they aren't already set
        $args['exclude_from_search'] = $args['exclude_from_search'] ?? ($public ? false : true);
        $args['publicly_queryable']  = $args['publicly_queryable'] ?? ($public ? true : false);
        $args['show_ui']             = $args['show_ui'] ?? ($public ? true : false);
        $args['show_in_menu']        = $args['show_in_menu'] ?? ($public ? true : false);
        $args['show_in_nav_menus']   = $args['show_in_nav_menus'] ?? ($public ? true : false);
        $args['show_in_admin_bar']   = $args['show_in_admin_bar'] ?? ($public ? true : false);
        $args['show_in_rest']        = $args['show_in_rest'] ?? ($public ? true : false);

        // Set rest base to the handle if not explicitly set
        $args['rest_base'] = isset($args['rest_base']) && $args['rest_base'] !== '' ? $args['rest_base'] : $this->handle;

        // Normalise the rewrite argument to an array if it's set to true
        if (isset($args['rewrite']) && $args['rewrite'] === true) {
            $args['rewrite'] = ['slug' => $this->handle];
        }

        $parsedArgs = [];
        // List of custom keys used by this class that aren't part of the standard register_post_type arguments
        $customKeys = ['use_block_editor'];

        foreach ($this->argumentKeys as $key) {
            $isCustom = in_array($key, $customKeys);
            $property = Str::camel($key);
            $propertyExists = property_exists($this, $property);
            
            if ($setAllProps) {
                if (array_key_exists($key, $args)) {

                    if ($propertyExists) {
                        $this->$property = $args[ $key ];
                    }
                    
                    if (! $isCustom) {
                        $parsedArgs[ $key ] = $args[ $key ];
                    }
                } 
                
                else if (! $isCustom) {
                    $parsedArgs[ $key ] = $propertyExists ? $this->$property : $args[ $key ] ?? null;
                } 
            }
            
            else {
                if (isset($args[ $key ]) && $propertyExists && ! isset($this->$property)) {
                    $this->$property = $args[ $key ];
                }

                if (! $isCustom && array_key_exists($key, $args)) {
                    $parsedArgs[ $key ] = $args[ $key ];
                }
            }
        }

        $this->parsedArguments = $parsedArgs;
    }

    /**
     * Parses the labels passed to the post type and sets the appropriate properties on the class instance
     *
     * @param  array $labels
     *
     * @return array The parsed labels
     */
    protected function parseLabels(array $labels = []): array {
        $parsedLabels = [
            'name'                      => $labels['name'] ?? $this->getPluralLabel(),
            'singular_name'             => $labels['singular_name'] ?? $this->getSingularLabel(),
            'add_new'                   => $labels['add_new'] ?? 'Add ' . $this->getSingularLabel(),
            'add_new_item'              => $labels['add_new_item'] ?? 'Add New ' . $this->getSingularLabel(),
            'edit_item'                 => $labels['edit_item'] ?? 'Edit ' . $this->getSingularLabel(),
            'new_item'                  => $labels['new_item'] ?? 'New ' . $this->getSingularLabel(),
            'view_item'                 => $labels['view_item'] ?? 'View ' . $this->getSingularLabel(),
            'view_items'                => $labels['view_items'] ?? 'View ' . $this->getPluralLabel(),
            'search_items'              => $labels['search_items'] ?? 'Search ' . $this->getPluralLabel(),
            'not_found'                 => $labels['not_found'] ?? 'No ' . $this->getPluralLabel() . ' found',
            'not_found_in_trash'        => $labels['not_found_in_trash'] ?? 'No ' . $this->getPluralLabel() . ' found in Trash',
            'parent_item_colon'         => $labels['parent_item_colon'] ?? 'Parent ' . $this->getSingularLabel() . ':',
            'all_items'                 => $labels['all_items'] ?? 'All ' . $this->getPluralLabel(),
            'archives'                  => $labels['archives'] ?? $this->getSingularLabel() . ' Archives',
            'attributes'                => $labels['attributes'] ?? $this->getSingularLabel() . ' Attributes',
            'insert_into_item'          => $labels['insert_into_item'] ?? 'Insert into ' . $this->getSingularLabel(),
            'uploaded_to_this_item'     => $labels['uploaded_to_this_item'] ?? 'Uploaded to this ' . $this->getSingularLabel(),
            'featured_image'            => $labels['featured_image'] ?? 'Featured Image',
            'set_featured_image'        => $labels['set_featured_image'] ?? 'Set featured image',
            'remove_featured_image'     => $labels['remove_featured_image'] ?? 'Remove featured image',
            'use_featured_image'        => $labels['use_featured_image'] ?? 'Use as featured image',
            'menu_name'                 => $labels['menu_name'] ?? $this->getPluralLabel(),
            'filter_items_list'         => $labels['filter_items_list'] ?? 'Filter ' . $this->getPluralLabel() . ' list',
            'items_list_navigation'     => $labels['items_list_navigation'] ?? $this->getPluralLabel() . ' list navigation',
            'items_list'                => $labels['items_list'] ?? $this->getPluralLabel() . ' list',
            'item_published'            => $labels['item_published'] ?? $this->getSingularLabel() . ' published',
            'item_published_privately'  => $labels['item_published_privately'] ?? $this->getSingularLabel() . ' published privately',
            'item_reverted_to_draft'    => $labels['item_reverted_to_draft'] ?? $this->getSingularLabel() . ' reverted to draft',
            'item_scheduled'            => $labels['item_scheduled'] ?? $this->getSingularLabel() . ' scheduled',
            'item_updated'              => $labels['item_updated'] ?? $this->getSingularLabel() . ' updated',
            'item_trashed'              => $labels['item_trashed'] ?? $this->getSingularLabel() . ' trashed',
            'item_link'                 => $labels['item_link'] ?? 'View ' . $this->getSingularLabel() . ' link',
            'item_link_description'     => $labels['item_link_description'] ?? 'A link to a ' . $this->getSingularLabel(),
        ];

        $this->labels = $parsedLabels;
        return $parsedLabels;
    }

    /***************************
     * Meta parsers & helpers
     ***************************/

    /**
     * Parses the meta fields passed to the post type
     *
     * @param  array $meta
     *
     * @return array The parsed meta fields
     */
    protected function parseMeta(array $meta): array {
        $parsedMeta = [];

        foreach ($meta as $metaField) {
            if (! isset($metaField['key'])) {
                continue;
            }
            
            $parsedMeta[] = [
                'key'  => $metaField['key'],
                'args' => [
                    'type'              => $metaField['type'] ?? 'string',
                    'label'             => $metaField['label'] ?? Str::title(str_replace(['-', '_'], ' ', $metaField['key'])),
                    'description'       => $metaField['description'] ?? '',
                    'single'            => $metaField['single'] ?? true,
                    'default'           => $metaField['default'] ?? null,
                    'sanitize_callback' => $metaField['sanitize_callback'] ?? [$this, 'sanitizeMetaValue'],
                    'auth_callback'     => $metaField['auth_callback'] ?? null,
                    'show_in_rest'      => $metaField['show_in_rest'] ?? true,
                    'revisions_enabled' => $metaField['revisions_enabled'] ?? false,
                ],
            ];
        }

        return $parsedMeta;
    }

    /**
     * Parses the meta boxes passed to the post type
     *
     * @param  array $metaBoxes
     *
     * @return array The parsed meta boxes
     */
    protected function parseMetaBoxes(array $metaBoxes): array {
        $parsedMetaBoxes = [];

        foreach ($metaBoxes as $metaBox) {
            $parsed = [
                'id'            => $metaBox['id'] ?? null,
                'title'         => $metaBox['title'] ?? Str::title(str_replace(['-', '_'], ' ', $metaBox['id'] ?? '')),
                'callback'      => $metaBox['callback'] ?? null,
                'callback_args' => $metaBox['callback_args'] ?? null,
                'context'       => $metaBox['context'] ?? 'advanced',
                'priority'      => $metaBox['priority'] ?? 'default'
            ];

            if (is_null($parsed['id']) || is_null($parsed['callback'])) {
                continue;
            }

            $parsedMetaBoxes[] = $parsed;
        }

        return $parsedMetaBoxes;
    }

    public function defaultMetaBoxCallback($post) {

    }

    /**
     * Default sanitization callback for meta fields.
     *
     * @param  mixed   $value
     * @param  string  $object_id
     * @param  string  $meta_key
     * @param  boolean $single
     *
     * @return mixed
     */
    public function sanitizeMetaValue(mixed $value, string $object_id, string $meta_key, bool $single): mixed {
        $metaField = collect($this->meta)->firstWhere('key', $meta_key);

        if (! $metaField) {
            return $value;
        }

        $requiredType = $metaField['args']['type'] ?? 'string';
        return Helpers::sanitize($value, $requiredType);
    }

    /***************
     * Helpers
     **************/

    /**
     * Generates and sets the singular label for the post type based on the handle if not explicitly set in the arguments.
     *
     * @return string
     */
    protected function getSingularLabel(): string {
        
        if (! isset($this->singularName) || $this->singularName === '') {
            $this->singularName = Str::singular(Str::title(str_replace(['-', '_'], ' ', $this->handle)));
        }

        return $this->singularName;
    }

    /**
     * Generates and sets the plural label for the post type based on the handle if not explicitly set in the arguments.
     *
     * @return string
     */
    protected function getPluralLabel(): string {
        if (! isset($this->pluralName) || $this->pluralName === '') {
            $this->pluralName = Str::plural(Str::title(str_replace(['-', '_'], ' ', $this->handle)));
        }

        return $this->pluralName;
    }

    protected function disableBlockEditor(): void {
        add_filter('use_block_editor_for_post_type', function ($use_block_editor, $post_type) {
            if ($post_type === $this->handle) {
                return false;
            }

            return $use_block_editor;
        }, 10, 2);
    }

    /**
     * Returns the result of the register_post_type function
     *
     * @return \WP_Post_Type|\WP_Error
     */
    public function getRegistrationResult(): \WP_Post_Type|\WP_Error {
        return $this->registrationResult;
    }
}