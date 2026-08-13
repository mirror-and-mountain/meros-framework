<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Forms\Field;
use MM\Meros\Services\Contracts\Forms\FieldGroup;
use MM\Meros\App\Fields\Repeater;

use MM\Meros\Services\Contracts\Interfaces\DataRegistrant;
use MM\Meros\Services\Contracts\Interfaces\AdminFieldRegistrant;

use MM\Meros\Services\Concerns\IsDataRegistrant;

use MM\Meros\Facades\Context;
use MM\Meros\Facades\FieldGroups;

use MM\Meros\Facades\PostMetaDefinitions;

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

    // =========================================================================
    // Initialisation
    // =========================================================================

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

        // Make sure the instance is attached to the register.
        PostMetaDefinitions::attach($this);
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
                    $this->getRegistrationArgs()
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
        if (!$this->queued) {
            $this->postType = $postType->handle;

            register_post_meta(
                $this->postType,
                $this->name,
                $this->getRegistrationArgs()
            );

            if ($this->fieldGroup !== null && !$this->metaBoxQueued) {
                $isEditing = Context::isEditingPostType($this->postType);

                if (!$isEditing || $this->metaBoxQueued) {
                    return;
                }

                add_action('add_meta_boxes', function() {
                    $fields = $this->fieldGroup->getFields(true);
                    $postID = get_post()->ID;
                    $value  = $this->getValue($postID);

                    if (!is_array($value)) {
                        $value = [];
                    }

                    foreach($fields as $field) {
                        $subKey = $field->getName(false);
                        $field->value($value[$subKey] ?? null);
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

    // =========================================================================
    // Default callbacks
    // =========================================================================

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

        if (!is_array($submittedData)) {
            $submittedData = [];
        }
        
        // Get existing meta to merge with
        $existingData = $this->getValue($postId);

        if (!is_array($existingData)) {
            $existingData = [];
        }

        // Merge submitted data with existing data, ensuring we only save defined sub-fields
        foreach ($this->fieldGroup->getFields(true) as $field) {
            $subKey = $field->getName(false); // Get the field name without the root path

            if ($field instanceof Repeater) {
                $existingData[$subKey] = $this->resolveSubmittedRepeaterRows(
                    $submittedData,
                    $subKey,
                    $field
                );
            }

            else if (array_key_exists($subKey, $submittedData)) {
                $existingData[$subKey] = $this->resolveSubmittedScalarArray(
                    $submittedData[$subKey],
                    $field
                );
            }

            else {
                // Handle unchecked checkboxes, empty selects, etc.
                $existingData[$subKey] = $field->getValue();
            }
        }

        // Save the merged data
        update_post_meta($postId, $this->name, $existingData);
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

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

    // =========================================================================
    // Field Management
    // =========================================================================

    /**
     * Overrides field() method from IsAdminFieldRegistrant to ensure the field is attached to the correct field group.
     *
    * @param  Field|string|null  $type     The type of field to add (e.g. 'text', 'checkbox', etc.), a Field instance, a Field class name, or null to infer the field type.
    * @param  Closure|array|null $callback Optional callback to configure the field, or props array for legacy calls.
    * @param  array              $props    Optional properties for the field.
    * @param  array              $args     Additional arguments for the field.
     *
     * @return Field
     */
    final public function field(Field|string|null $type = null, Closure|array|null $callback = null, array $props = [], array $args = []): Field {
        $params = func_num_args();

        // Legacy signature support: field($type, $props, $args)
        if ($params >= 3 && is_array($callback)) {
            $legacyProps = $callback;

            if ($params === 2) {
                $props = $legacyProps;
                $callback = null;
            }

            else if ($params === 3 && is_array($props)) {
                $args = $props;
                $props = $legacyProps;
                $callback = null;
            }
        }

        $this->makeField($type, $callback, $props, $args);
        $this->syncFieldPresentationFromMetaDefinition();
        $this->getFieldGroup()->field($this->field);

        return $this->field;
    }

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

    /**
     * Detects a raw repeater rows payload keyed by numeric row indexes.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function looksLikeRepeaterRows(mixed $value): bool {
        if (!is_array($value) || empty($value)) {
            return false;
        }

        $keys = array_keys($value);

        foreach ($keys as $key) {
            if (!(is_int($key) || ctype_digit((string) $key))) {
                return false;
            }
        }

        $firstRow = reset($value);
        return is_array($firstRow);
    }

    /**
     * Extracts repeater rows from malformed/mixed submitted payloads.
     *
     * Handles shapes like:
     * - _meta[0][child]
     * - _meta[text] plus _meta[0][child]
     *
     * Returns null when no numeric row payload can be inferred.
     *
     * @param mixed    $submittedData
     * @param Repeater $field
     *
     * @return array|null
     */
    protected function extractRepeaterRowsFromSubmittedData(mixed $submittedData, Repeater $field): ?array {
        if (!is_array($submittedData)) {
            return null;
        }

        $candidateRows = [];

        foreach ($submittedData as $key => $row) {
            if ((is_int($key) || ctype_digit((string) $key)) && is_array($row)) {
                $candidateRows[] = $row;
            }
        }

        if (empty($candidateRows)) {
            return null;
        }

        $subFieldNames = [];

        foreach ($field->getFields(true) as $subField) {
            if ($subField instanceof Field) {
                $name = $subField->getName(false);

                if (is_string($name) && $name !== '') {
                    $subFieldNames[] = $name;
                }
            }
        }

        if (empty($subFieldNames)) {
            return array_values($candidateRows);
        }

        $filteredRows = [];

        foreach ($candidateRows as $row) {
            $hasExpectedKey = false;

            foreach ($subFieldNames as $subFieldName) {
                if (array_key_exists($subFieldName, $row)) {
                    $hasExpectedKey = true;
                    break;
                }
            }

            if ($hasExpectedKey) {
                $filteredRows[] = $row;
            }
        }

        return array_values($filteredRows);
    }

    /**
     * Resolves submitted repeater rows for a specific repeater key.
     *
     * @param array    $submittedData
     * @param string   $subKey
     * @param Repeater $field
     *
     * @return array
     */
    protected function resolveSubmittedRepeaterRows(array $submittedData, string $subKey, Repeater $field): array {
        $hasExplicitRepeaterKey = array_key_exists($subKey, $submittedData);
        $submittedRepeater      = $hasExplicitRepeaterKey ? $submittedData[$subKey] : null;

        if (is_array($submittedRepeater) && array_key_exists('__empty', $submittedRepeater)) {
            unset($submittedRepeater['__empty']);
        }

        // Preferred shape: _meta[repeater_key][0][child]
        $rows = $this->extractRepeaterRowsFromSubmittedData($submittedRepeater, $field);

        if ($rows !== null) {
            return $rows;
        }

        // If the repeater key exists but no rows are present, this is an explicit
        // empty-state submission (e.g. marker-only payload) and should persist [].
        if ($hasExplicitRepeaterKey) {
            return [];
        }

        // Backward-compat fallback for malformed/mixed payloads like:
        // _meta[0][child] or _meta[text] + _meta[0][child].
        $rows = $this->extractRepeaterRowsFromSubmittedData($submittedData, $field);

        return $rows ?? [];
    }

    /**
     * Resolves submitted scalar-array payloads (e.g. multi_select) and strips
     * explicit empty markers while preserving intentional empty submissions.
     *
     * @param mixed $value
     * @param Field $field
     *
     * @return mixed
     */
    protected function resolveSubmittedScalarArray(mixed $value, Field $field): mixed {
        if ($field->getDataType() !== 'array' || !is_array($value)) {
            return $value;
        }

        if (array_key_exists('__empty', $value)) {
            unset($value['__empty']);
        }

        return array_values($value);
    }

    /**
     * Builds registration args for register_post_meta with safe defaults for
     * typed meta containers.
     *
     * @return array
     */
    protected function getRegistrationArgs(): array {
        $args = $this->args;

        // A null default is not valid for typed meta registration in WordPress.
        // Only pass defaults when explicitly set to a concrete value.
        if (array_key_exists('default', $args) && $args['default'] === null) {
            unset($args['default']);
        }

        if (
            in_array($this->type, ['array', 'object'], true)
            && (($args['show_in_rest'] ?? false) === true)
        ) {
            $args['show_in_rest'] = ['schema' => $this->toSchema()];
        }

        return $args;
    }

    /**
     * Keeps the rendered field label/help text aligned with the meta definition.
     *
     * @return void
     */
    protected function syncFieldPresentationFromMetaDefinition(): void {
        if (!$this->field instanceof Field) {
            return;
        }

        if (!empty($this->args['label'])) {
            $this->field->label((string) $this->args['label']);
        }

        if (!empty($this->args['description'])) {
            $this->field->helpText((string) $this->args['description']);
        }
    }

    // =========================================================================
    // Getters
    // =========================================================================

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
}