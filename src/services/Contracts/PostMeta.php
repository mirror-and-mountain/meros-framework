<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FieldGroup;

use MM\Meros\Services\Contracts\Interfaces\DataRegistrant;
use MM\Meros\Services\Contracts\Interfaces\AdminFieldRegistrant;

use MM\Meros\Services\Concerns\IsDataRegistrant;

use MM\Meros\Facades\Context;
use MM\Meros\Facades\FieldGroups;

use Illuminate\Support\Facades\Log;

class PostMeta extends FeatureDefinition implements DataRegistrant, AdminFieldRegistrant {

    /**
     * The post type that this meta belongs to.
     *
     * @var string
     */
    protected string $postType = '';

    /**
     * Indicates whether the meta box associated with this post meta has been queued for rendering.
     *
     * @var boolean
     */
    protected bool $metaBoxQueued = false;

    /**
     * The field group associated with this post meta, if any
     *
     * @var FieldGroup|null
     */
    protected ?FieldGroup $fieldGroup = null;

    use IsDataRegistrant {
        IsDataRegistrant::field as protected makeField;
    }

    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        $this->setDefaultArgs();
        $this->setProps($props);

        if ($this->args['sanitize_callback'] === null) {
            $this->args['sanitize_callback'] = [$this, 'sanitize'];
        }

        if ($this->args['auth_callback'] === null) {
            $this->args['auth_callback'] = [$this, 'authenticate'];
        }

        if (is_string($this->field) && !empty($this->field)) {
            $this->field($this->field);
        }

        if ($this->canBeParent()) {
            $this->instantiateSubItems();
        }
    }

    /**
     * Sets default arguments for the setting.
     *
     * @return void
     */
    final protected function setDefaultArgs(): void {
        $this->args = array_merge($this->args, [
            'type'              => '',
            'label'             => '',
            'description'       => '',
            'single'            => true,
            'default'           => null,
            'show_in_rest'      => false,
            'sanitize_callback' => null,
            'auth_callback'     => null,
        ]);
    }

    /**
     * Hooks into the 'init' action to register the post meta with WordPress.
     *
     * @return void
     */
    protected function queue(): void {
        if (!$this->isRoot() || empty($this->name)) {
            return;
        }

        if (empty($this->postType)) {
            return;
        }

        if (!$this->queued) {
            add_action('init', function () {
                register_post_meta(
                    $this->postType,
                    $this->name,
                    $this->args
                );
            });
        }

        $this->queued = true;
    }

    /**
     * Queues the post meta for registration through a specific post type. Should be called from the PostType contract when registering meta containers.
     *
     * @param PostType $postType
     *
     * @return void
     */
    final public function queueFromPostType(PostType $postType): void {
        if ($this->isRoot() && !$this->queued) {
            $this->postType = $postType->handle;

            register_post_meta(
                $this->postType,
                $this->name,
                $this->args
            );

            if ($this->fieldGroup !== null && !$this->metaBoxQueued) {
                $isEditing = Context::isEditingPostType($this->postType);

                if (!$isEditing || $this->metaBoxQueued) {
                    return;
                }

                add_action('add_meta_boxes', function() {
                    $fields = $this->fieldGroup->getFields(true);
                    $postID = get_post()->ID;
                    $value  = $this->getValue($postID) ?? [];

                    foreach($fields as $field) {
                        $field->value($value[$field->getName(false)] ?? null);
                        $field->default($this->getDefault());
                    }

                    add_meta_box(
                        $this->name . '_meta_box',
                        $this->args['label'] ?: Str::title(str_replace('_', ' ', $this->name)),
                        function() {
                            wp_nonce_field(
                                $this->name . '_save_meta',
                                $this->name . '_meta_nonce'
                            );
                            $this->fieldGroup->render();
                        },
                        $this->postType,
                        'advanced',
                        'default'
                    );
                });

                $this->metaBoxQueued = true;
            }

            $this->queued = true;
        }
    }

    /***************************
     * Default Callbacks
     ***************************/
    /**
     * The default authentication callback for the post meta, which checks if the current user has permission to edit posts.
     *
     * @return bool
     */
    final public function authenticate(): bool {
        return current_user_can('edit_posts');
    }

    /**
     * Saves the post meta value when the post is saved.
     *
     * @param string $postId
     *
     * @return void
     */
    final public function save(string $postId): void {
        // Bail if not in admin context
        if (!Context::isAdmin()) {
            return;
        }

        // Bail if this is not a root definition
        if (!$this->isRoot()) {
            return;
        }

        // Bail if autosaving
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Verify nonce
        $nonceKey = $this->name . '_meta_nonce';

        if (!isset($_POST[$nonceKey]) || 
            !wp_verify_nonce($_POST[$nonceKey], $this->name . '_save_meta')
        ) {
            return;
        }

        // Check user permissions
        if ($this->authenticate() === false) {
            return;
        }

        // Get submitted data
        $submittedData = $_POST[$this->name] ?? [];
        
        // Get existing meta to merge with
        $existingData = $this->getValue($postId);

        // Merge submitted data with existing data, ensuring we only save defined sub-fields
        foreach ($this->fieldGroup->getFields(true) as $field) {
            $subKey = $field->getName(false); // Get the field name without the root path

            if (array_key_exists($subKey, $submittedData)) {
                $existingData[$subKey] = $submittedData[$subKey];
            } else {
                // Handle unchecked checkboxes, empty selects, etc.
                $existingData[$subKey] = $field->getValue();
            }
        }

        // Save the merged data
        update_post_meta($postId, $this->name, $existingData);
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the key for the post meta. This is required for the post meta to be registered.
     *
     * @param string $key The meta key to register.
     *
     * @return self
     */
    public function key(string $key): self {
        $this->name = Str::snake($key); // default name to key if not explicitly set

        $this->queue();
        return $this;
    }

    /**
     * Sets the authentication callback for the post meta.
     *
     * @param callable|Closure $callback A callable or method reference for authenticating access to the post meta.
     *
     * @return self
     */
    public function authCallback(callable|Closure $callback): self {
        $this->args['auth_callback'] = $this->convertToClosure($callback);

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post meta should be treated as a single value or multiple values (array).
     *
     * @param bool $single If true, the post meta will be treated as a single value; if false, as multiple values (array).
     *
     * @return self
     */
    public function single(bool $single = true): self {
        $this->args['single'] = $single;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the post meta should be treated as multiple values (array) or a single value.
     *
     * @param bool $multiple If true, the post meta will be treated as multiple values; if false, as a single value.
     *
     * @return self
     */
    public function multiple(bool $multiple = true): self {
        return $this->single(!$multiple);
    }

    /**
     * Overrides field() method from IsAdminFieldRegistrant to ensure the field is attached to the correct field group.
     *
     * @param  Field|string|null $type    The type of field to add (e.g. 'text', 'checkbox', etc.), a Field instance, a Field class name, or null to infer the field type.
     * @param  array             $props   Optional properties for the field.
     * @param  array             $args    Additional arguments for the field. Not used by default, but may be used in child overrides of this method.
     *
     * @return Field
     */
    final public function field(Field|string|null $type = null, array $props = [], array $args = []): Field {
        $this->makeField($type, $props, $args);
        $this->getFieldGroup()->field($this->field);

        return $this->field;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Retrieves the value of the post meta for a given post ID. If no post ID is provided, it will attempt to use the global $post.
     *
     * @param int|null $postId Optional post ID to retrieve the meta for. If not provided, uses the global $post.
     *
     * @return mixed The value of the post meta, or null if not found or if required properties are missing.
     */
    final public function getValue(?int $postId = null): mixed {
        // If required properties are missing, return null
        if (empty($this->name)) {
            return null;
        }

        if ($this->isRoot() && empty($this->postType)) {
            return null;
        }

        $postType = $this->getPostType();
        $post     = get_post($postId);

        // If no post ID is provided, attempt to use the global $post
        if ($post === null) {
            $post = get_post();
            $postId = $post->ID ?? null;
        }

        // If we still don't have a post ID, return null
        if (is_null($postId)) {
            return null;
        }

        // If the post type doesn't match, return null
        if ($post->post_type !== $postType) {
            return null;
        }

        // Traverse up to the root of the post meta structure
        $root = $this;
        while ($root->parent !== null) {
            $root = $root->parent;
        }

        // Retrieve the post meta value using get_post_meta
        $value = get_post_meta($postId, $root->getName(), $this->args['single']);

        if ($this->isRoot()) {
            // Merge with default values from sub-items 
            if (!empty($this->subItems)) {
                $value = is_array($value) ? $value : [];

                foreach ($root->getSubItems() as $subItem) {
                    if (!isset($value[$subItem->name])) {
                        $value[$subItem->name] = $subItem->getValue($postId);
                    }
                }

                return $value;
            }

            return $value;
        }

        return $value[$this->name] ?? ($this->args['default'] ?? null);
    }

    /**
     * Retrieves the default value of the post meta.
     *
     * @return mixed
     */
    public function getDefault(): mixed {
        return $this->args['default'] ?? null;
    }

    /**
     * Retrieves the post type that this meta belongs to. If this is a nested meta, it will traverse up to the root to get the post type.
     *
     * @return string|null The post type this meta belongs to, or null if not set.
     */
    public function getPostType(): ?string {
        if ($this->isRoot()) {
            return $this->postType;
        }

        return $this->parent->getPostType();
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Retrieves the root field group for this post meta, creating it if it doesn't exist.
     *
     * @return FieldGroup
     */
    public function getFieldGroup(): FieldGroup {
        if ($this->isRoot()) {
            if ($this->fieldGroup === null) {
                $this->fieldGroup = FieldGroups::checkout($this->provider)->make([
                    'handle'      => $this->name . '_field_group',
                    'title'       => $this->args['label'] ?? Str::title(str_replace('_', ' ', $this->name)) . ' Fields',
                    'description' => $this->args['description'] ?? '',
                ]);

                $this->fieldGroup->parentMeta($this);
            }

            return $this->fieldGroup;
        }

        else {
            return $this->parent->getFieldGroup();
        }
    }

    /**
     * Sets an existing field group to be used for this post meta.
     *
     * @param FieldGroup $fieldGroup
     *
     * @return self
     */
    public function setFieldGroup(FieldGroup $fieldGroup): self {
        if (!$this->isRoot()) {
            throw new \LogicException("Cannot set field group on a non-root post meta. Field groups can only be set on root post meta instances.");
        }

        $this->fieldGroup = $fieldGroup;
        $this->fieldGroup->parentMeta($this);
        return $this;
    }
}