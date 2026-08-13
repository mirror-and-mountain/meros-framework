<?php

namespace MM\Meros\Contracts\Features\Data;

use Closure;

use MM\Meros\Contracts\Features\Data\DataContainer;

use MM\Meros\Contracts\Features\Components\Field;
use MM\Meros\Contracts\Features\Components\FieldGroup;

class PostMetaContainer extends DataContainer {
    /**
     * The prefix to be used with the container's name.
     *
     * @var string
     */
    final protected string $prefix = '_meros';

    /**
     * An array of arguments for registering the post meta.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * An array of post type handle's (names) to be associated with the PostMeta.
     *
     * @var array<string>
     */
    protected array $postTypes = [];

    /**
     * The current post ID for which the PostMeta value is being retrieved.
     *
     * @var integer|null
     */
    protected ?int $currentPostId = null;

    /**
     * The capability required to edit the post meta.
     *
     * @var string
     */
    protected string $capability = 'edit_posts';

    /**
     * The field group definition associated with this PostMetaContainer.
     * 
     * This can be a string representing the field group id or an array of field group properties.
     *
     * @var string|array
     */
    protected string|array $fieldGroup = '';

    /**
     * The FieldGroup instance associated with this PostMetaContainer.
     *
     * @var FieldGroup|null
     */
    protected ?FieldGroup $fieldGroupInstance = null;

    /**
     * Indicates whether a meta box has been hooked for this PostMetaContainer.
     *
     * @var boolean
     */
    private bool $metaBoxHooked = false;

    /**
     * The context in which the meta box should be displayed.
     *
     * @var string
     */
    protected string $metaBoxContext = 'advanced';

    /**
     * The priority of the meta box.
     *
     * @var string
     */
    protected string $metaBoxPriority = 'default';

    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function init(): void {
        if (is_string($this->passedProps['name'] ?? '') && !empty($this->passedProps['name'])) {
            $this->set('name', $this->passedProps['name']);
        }

        $this->set('label', $this->passedProps['label'] ?? '');
        $this->set('description', $this->passedProps['description'] ?? '');

        $this->setHook('init');
        $this->setItemClass(PostMeta::class);
    }

    final protected function whenConfigured(): void {
        parent::whenConfigured();

        if (is_array($this->fieldGroup) && !empty($this->fieldGroup)) {
            $id = $this->fieldGroup['id'] ?? null;
            
            if ($id !== null) {
                $this->fieldGroup($id, $this->fieldGroup['properties'] ?? []);
            }
        }

        else if (is_string($this->fieldGroup) && !empty($this->fieldGroup)) {
            $this->fieldGroup($this->fieldGroup);
        }
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    /**
     * Registers the post meta container with the associated post types.
     *
     * @return void
     */
    final public function registerContainer(): void {
        if (empty($this->postTypes) || empty($this->name)) {
            return;
        }

        $args = array_merge($this->args, [
            'type'              => 'object',
            'label'             => $this->label,
            'description'       => $this->description,
            'default'           => $this->getDefault(),
            'sanitize_callback' => [$this, 'sanitizeValue'],
            'single'            => true,
            'show_in_rest'      => $this->showInRest ? $this->getSchema() : false,
        ]);

        if (!isset($args['auth_callback'])) {
            $args['auth_callback'] = [$this, 'defaultAuthCallback'];
        }

        foreach ($this->postTypes as $postType) {
            register_post_meta($postType, $this->name, $args);
            add_action('save_post_' . $postType, [$this, '__savePost']);
        }

        if ($this->fieldGroupInstance !== null) {
            $this->registerMetaBoxes();
        }
    }

    /**
     * Registers meta boxes for the associated post types, if a FieldGroup is associated with this PostMetaContainer.
     *
     * @return void
     */
    private function registerMetaBoxes(): void {
        if ($this->metaBoxHooked) {
            return;
        }

        foreach ($this->postTypes as $postType) {
            add_action('add_meta_boxes_' . $postType, function () use ($postType) {
                add_meta_box(
                    $this->name . '_meta_box',
                    $this->label,
                    [$this, 'renderMetaBox'],
                    $postType,
                    $this->metaBoxContext,
                    $this->metaBoxPriority
                );
            });
        }

        $this->metaBoxHooked = true;
    }

    /**
     * Renders the meta box for the associated post types.
     *
     * @param \WP_Post $post The current post object.
     *
     * @return void
     */
    final public function renderMetaBox(\WP_Post $post): void {
        $this->currentPostId = $post->ID;

        if ($this->fieldGroupInstance !== null) {
            $values = $this->getValue(true);

            echo '<div class="meros-meta-box">';
            if (!empty($this->description)) {
                echo '<p class="description">' . esc_html($this->description) . '</p>';
            }

            echo $this->fieldGroupInstance->__renderAsMetaBox($this->name, $values);
            wp_nonce_field($this->name . '_meta_box_nonce', $this->name . '_meta_box_nonce_field');
            echo '</div>';
        }
    }

    /**
     * Saves the post meta value for an associated post type when a post is saved.
     * 
     * For internal use only.
     *
     * @param int $postId The ID of the post being saved.
     *
     * @return void
     */
    final public function __savePost(int $postId): void {
        if ($this->fieldGroupInstance === null) {
            return;
        }

        if (!isset($_POST[$this->name . '_meta_box_nonce_field']) || 
            !wp_verify_nonce($_POST[$this->name . '_meta_box_nonce_field'], $this->name . '_meta_box_nonce')
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!in_array($_POST['post_type'] ?? '', $this->postTypes)) {
            return;
        }

        if (!current_user_can($this->capability, $postId)) {
            return;
        }

        if (!isset($_POST[$this->name]) || !is_array($_POST[$this->name])) {
            return;
        }

        $this->savePostMeta($postId, $_POST[$this->name]);
    }

    /**
     * Saves the post meta value for the given post ID.
     *
     * @param int   $postId The ID of the post to save the meta for.
     * @param array $value  The value to save as post meta.
     *
     * @return void
     */
    private function savePostMeta(int $postId, array $value): void {
        $sanitizedValue = $this->sanitizeValue($value);
        update_post_meta($postId, $this->name, $sanitizedValue);
    }

    /**
     * Unregisters the post meta container from the associated post types.
     *
     * @return void
     */
    final public function unregisterContainer(): void {
        foreach ($this->postTypes as $postType) {
            unregister_post_meta($postType, $this->name);
        }
    }

    /**
     * The default authentication callback for the post meta.
     *
     * @return boolean
     */
    final public function defaultAuthCallback(): bool {
        return current_user_can($this->capability);
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the post types to be associated with the PostMeta.
     *
     * @param array $postTypes
     *
     * @return static
     */
    final public function postTypes(array $postTypes): static {
        $this->postTypes = $postTypes;
        return $this;
    }

    /**
     * Adds a single post type to be associated with the PostMeta.
     *
     * @param string $postType
     *
     * @return static
     */
    final public function postType(string $postType): static {
        if (!in_array($postType, $this->postTypes)) {
            $this->postTypes[] = $postType;
        }

        return $this;
    }

    /**
     * Sets the authentication callback for the post meta.
     *
     * @param Closure $callback
     *
     * @return static
     */
    final public function authenticate(Closure $callback): static {
        $this->args['auth_callback'] = $callback;
        return $this;
    }

    /**
     * Sets the capability required to edit the post meta.
     *
     * @param string $capability
     *
     * @return static
     */
    final public function capability(string $capability): static {
        $this->capability = $capability;
        return $this;
    }

    /**
     * Sets the current post ID for the PostMeta. 
     * 
     * This is used to retrieve the post meta value for a specific post.
     *
     * @param int $postId
     *
     * @return static
     */
    final public function currentPostId(int $postId): static {
        $this->currentPostId = $postId;
        return $this;
    }

    /**
     * Sets the context in which the meta box should be displayed.
     *
     * @param string $context
     *
     * @return static
     */
    final public function metaBoxContext(string $context): static {
        $this->metaBoxContext = $context;
        return $this;
    }

    /**
     * Sets the context in which the meta box should be displayed. Alias for `metaBoxContext`.
     *
     * @param string $context
     *
     * @return static
     */
    final public function context(string $context): static {
        return $this->metaBoxContext($context);
    }

    /**
     * Sets the priority of the meta box.
     *
     * @param string $priority
     *
     * @return static
     */
    final public function metaBoxPriority(string $priority): static {
        $this->metaBoxPriority = $priority;
        return $this;
    }

    /**
     * Sets the priority of the meta box. Alias for `metaBoxPriority`.
     *
     * @param string $priority
     *
     * @return static
     */
    final public function priority(string $priority): static {
        return $this->metaBoxPriority($priority);
    }

    // =========================================================================
    // FieldGroup Association
    // =========================================================================

    /**
     * Associates a FieldGroup with this PostMetaContainer. If a FieldGroup is already associated, it returns the existing instance.
     *
     * @param string $id
     * @param array  $callbackOrProps
     *
     * @return FieldGroup
     */
    final public function fieldGroup(string $id = '', Closure|array $callbackOrProps = []): FieldGroup {
        if ($this->fieldGroupInstance !== null) {
            return $this->fieldGroupInstance;
        }

        if (!empty($id)) {
            $fieldGroupInstance = $this->makeItemFrom($id, FieldGroup::class, $callbackOrProps);
    
            if (!($fieldGroupInstance instanceof FieldGroup)) {
                throw new \LogicException("The created field group must be an instance of FieldGroup.");
            }

            $this->fieldGroupInstance = $fieldGroupInstance;
            return $this->fieldGroupInstance;
        }

        $fieldGroupInstance = $this->makeItem(FieldGroup::class, function (FieldGroup $fieldGroup) {
            $fieldGroup->id($this->name . '_field_group');
            $fieldGroup->title($this->label);
            $fieldGroup->description($this->description);
        });

        if (!($fieldGroupInstance instanceof FieldGroup)) {
            throw new \LogicException("The created field group must be an instance of FieldGroup.");
        }

        $this->fieldGroupInstance = $fieldGroupInstance;
        return $this->fieldGroupInstance;
    }

    /**
     * Adds a field to the associated FieldGroup of this PostMetaContainer.
     * 
     * For internal use only. Use the `field` method on the PostMeta item instead.
     *
     * @param string  $type
     * @param array   $callbackOrProps
     * @param boolean $autoRow
     *
     * @return Field
     */
    final public function __field(
        string        $type,
        Closure|array $callbackOrProps = [], 
        bool          $autoRow = true,
    ): Field {
        $fieldGroup = $this->fieldGroup();

        $field = $fieldGroup->field($type, $callbackOrProps, $autoRow, true);
        return $field;
    }

    // =========================================================================
    // Sanitization and Value Processing
    // =========================================================================

    /**
     * Retrieves the value of the post meta for the current post ID.
     *
     * @param boolean $refresh
     *
     * @return array
     * @throws \RuntimeException if the current post ID is not set.
     */
    public function getValue(bool $refresh = false): array {
        if ($this->currentPostId === null) {
            throw new \RuntimeException("Current post ID is not set. Use the 'currentPostId' method to set it before retrieving the value.");
        }

        return parent::getValue($refresh);
    }

    /**
     * Retrieves the value of the post meta for a specific post ID.
     * 
     * Allows for method chaining when no post ID is provided, returning the instance itself. 
     * 
     * Example: $postMetaContainer->value(123); // Retrieves the value for post ID 123
     * Example: $postMetaContainer->value()->for(123); // Retrieves the value for post ID 123
     * 
     * @param integer|null $forPostId
     * @param bool         $refresh
     *
     * @return array|static
     */
    final public function value(?int $forPostId = null, bool $refresh = false): array|static {
        if ($forPostId === null) {
            return $this;
        }

        $this->currentPostId = $forPostId;
        return $this->getValue($refresh);
    }

    /**
     * Retrieves the value of the post meta for a specific post ID.
     *
     * @param integer $postId
     * @param bool    $refresh
     *
     * @return array
     */
    final public function for(int $postId, bool $refresh = false): array {
        $this->currentPostId = $postId;
        return $this->getValue($refresh);
    }

    /**
     * Retrieves the raw value of the post meta for the current post ID.
     *
     * @return array
     */
    final protected function getRawValue(): array {
        $rawValue = get_post_meta($this->currentPostId, $this->name, true);

        if (!is_array($rawValue)) {
            $rawValue = [];
        }

        return $rawValue;
    }

    /**
     * Processes the raw value of the post meta before returning it.
     *
     * @param array $rawValue
     *
     * @return array
     */
    final protected function processRawValue(array $rawValue): array {
        return $rawValue;
    }
}