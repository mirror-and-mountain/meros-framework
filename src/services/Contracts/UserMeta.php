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
use MM\Meros\Facades\UserMetaDefinitions;

class UserMeta extends FeatureDefinition implements DataRegistrant, AdminFieldRegistrant {
    /**
     * Indicates whether the profile section associated with this user meta has
     * been queued for rendering.
     *
     * @var boolean
     */
    protected bool $profileSectionQueued = false;

    /**
     * The field group associated with this user meta, if any.
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

        UserMetaDefinitions::attach($this);
    }

    /**
     * Sets default arguments for the user meta registration.
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
     * Hooks into WordPress to register the user meta and queue profile field
     * rendering and persistence.
     *
     * @return void
     */
    protected function queue(): void {
        if (!$this->isRoot() || empty($this->name)) {
            return;
        }

        if (!$this->queued) {
            add_action('init', function () {
                register_meta(
                    'user',
                    $this->name,
                    $this->getRegistrationArgs()
                );
            });

            add_action('personal_options_update', function ($userId) {
                $this->save((int) $userId);
            });

            add_action('edit_user_profile_update', function ($userId) {
                $this->save((int) $userId);
            });

            $this->queued = true;
        }

        $this->queueProfileSection();
    }

    /***************************
     * Default Callbacks
     ***************************/
    /**
     * The default authentication callback for the user meta.
     *
     * @return bool
     */
    final public function authenticate(): bool {
        return current_user_can('edit_users');
    }

    /**
     * Saves the user meta value when a profile is updated.
     *
     * @param int|string $userId
     *
     * @return void
     */
    final public function save(int|string $userId): void {
        if (!Context::isAdmin()) {
            return;
        }

        if (!$this->isRoot()) {
            return;
        }

        if ($this->fieldGroup === null) {
            return;
        }

        $userId = (int) $userId;

        if ($userId <= 0) {
            return;
        }

        $nonceKey = $this->name . '_meta_nonce';

        if (
            !isset($_POST[$nonceKey])
            || !wp_verify_nonce($_POST[$nonceKey], $this->name . '_save_meta')
        ) {
            return;
        }

        if ($this->canEditUser($userId) === false) {
            return;
        }

        $submittedData = $_POST[$this->name] ?? [];

        if (!is_array($submittedData)) {
            $submittedData = [];
        }

        $existingData = $this->getValue($userId);

        if (!is_array($existingData)) {
            $existingData = [];
        }

        foreach ($this->fieldGroup->getFields(true) as $field) {
            $subKey = $field->getName(false);

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
                $existingData[$subKey] = $field->getValue();
            }
        }

        update_user_meta($userId, $this->name, $existingData);
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the key for the user meta.
     *
     * @param string $key
     *
     * @return self
     */
    public function key(string $key): self {
        $this->name = Str::snake($key);

        $this->queue();
        return $this;
    }

    /**
     * Sets the authentication callback for the user meta.
     *
     * @param callable|Closure $callback
     *
     * @return self
     */
    public function authCallback(callable|Closure $callback): self {
        $this->args['auth_callback'] = $this->convertToClosure($callback);

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the user meta should be treated as a single value.
     *
     * @param bool $single
     *
     * @return self
     */
    public function single(bool $single = true): self {
        $this->args['single'] = $single;

        $this->queue();
        return $this;
    }

    /**
     * Sets whether the user meta should be treated as multiple values.
     *
     * @param bool $multiple
     *
     * @return self
     */
    public function multiple(bool $multiple = true): self {
        return $this->single(!$multiple);
    }

    /**
     * Overrides field() to ensure the field is attached to the correct field group.
     *
     * @param Field|string|null  $type
     * @param Closure|array|null $callback
     * @param array              $props
     * @param array              $args
     *
     * @return Field
     */
    final public function field(Field|string|null $type = null, Closure|array|null $callback = null, array $props = [], array $args = []): Field {
        $params = func_num_args();

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
        $this->queue();

        return $this->field;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Retrieves the value of the user meta for a given user ID.
     *
     * @param int|null $userId
     *
     * @return mixed
     */
    final public function getValue(?int $userId = null): mixed {
        if (empty($this->name)) {
            return null;
        }

        $user = $userId !== null ? get_userdata($userId) : wp_get_current_user();
        $resolvedUserId = $user instanceof \WP_User ? (int) $user->ID : 0;

        if ($resolvedUserId <= 0) {
            return null;
        }

        $root = $this;
        while ($root->parent !== null) {
            $root = $root->parent;
        }

        $value = get_user_meta($resolvedUserId, $root->getName(), $this->args['single']);

        if ($this->isRoot()) {
            if (!empty($this->subItems)) {
                $value = is_array($value) ? $value : [];

                foreach ($root->getSubItems() as $subItem) {
                    if (!isset($value[$subItem->name])) {
                        $value[$subItem->name] = $subItem->getValue($resolvedUserId);
                    }
                }

                return $value;
            }

            return $value;
        }

        return $value[$this->name] ?? ($this->args['default'] ?? null);
    }

    /**
     * Retrieves the default value of the user meta.
     *
     * @return mixed
     */
    public function getDefault(): mixed {
        return $this->args['default'] ?? null;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Retrieves the root field group for this user meta.
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

            $this->queueProfileSection();

            return $this->fieldGroup;
        }

        if (!$this->parent instanceof self) {
            throw new \LogicException('User meta parent must be another user meta definition.');
        }

        return $this->parent->getFieldGroup();
    }

    /**
     * Sets an existing field group to be used for this user meta.
     *
     * @param FieldGroup $fieldGroup
     *
     * @return self
     */
    public function setFieldGroup(FieldGroup $fieldGroup): self {
        if (!$this->isRoot()) {
            throw new \LogicException('Cannot set field group on a non-root user meta. Field groups can only be set on root user meta instances.');
        }

        $this->fieldGroup = $fieldGroup;
        $this->fieldGroup->parentMeta($this);
        $this->queue();
        $this->queueProfileSection();

        return $this;
    }

    /**
     * Queues the profile section rendering hooks when fields exist.
     *
     * @return void
     */
    protected function queueProfileSection(): void {
        if (!$this->isRoot() || $this->fieldGroup === null || $this->profileSectionQueued) {
            return;
        }

        $render = function (\WP_User $user): void {
            $fields = $this->fieldGroup->getFields(true);
            $value = $this->getValue((int) $user->ID);

            if (!is_array($value)) {
                $value = [];
            }

            foreach ($fields as $field) {
                $subKey = $field->getName(false);
                $field->value($value[$subKey] ?? null);
            }

            echo '<h2>' . esc_html($this->args['label'] ?: Str::title(str_replace('_', ' ', $this->name))) . '</h2>';

            if (!empty($this->args['description'])) {
                echo '<p>' . esc_html($this->args['description']) . '</p>';
            }

            echo '<div class="meros-user-meta-group">';

            wp_nonce_field(
                $this->name . '_save_meta',
                $this->name . '_meta_nonce'
            );

            $this->fieldGroup->render([
                'showTitle' => false,
                'showDescription' => false,
                'class' => 'meros-user-meta-group__fields',
            ]);

            echo '</div>';
        };

        add_action('show_user_profile', $render);
        add_action('edit_user_profile', $render);

        $this->profileSectionQueued = true;
    }

    /**
     * Determines whether the current request can edit the target user.
     *
     * @param int $userId
     *
     * @return bool
     */
    protected function canEditUser(int $userId): bool {
        return current_user_can('edit_user', $userId) || current_user_can('edit_users');
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
        $submittedRepeater = $hasExplicitRepeaterKey ? $submittedData[$subKey] : null;

        if (is_array($submittedRepeater) && array_key_exists('__empty', $submittedRepeater)) {
            unset($submittedRepeater['__empty']);
        }

        $rows = $this->extractRepeaterRowsFromSubmittedData($submittedRepeater, $field);

        if ($rows !== null) {
            return $rows;
        }

        if ($hasExplicitRepeaterKey) {
            return [];
        }

        $rows = $this->extractRepeaterRowsFromSubmittedData($submittedData, $field);

        return $rows ?? [];
    }

    /**
     * Resolves submitted scalar-array payloads and strips explicit empty markers.
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
     * Builds registration args for register_meta with safe defaults.
     *
     * @return array
     */
    protected function getRegistrationArgs(): array {
        $args = $this->args;

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
}
