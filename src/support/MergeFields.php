<?php 

namespace MM\Meros\Support;

use Illuminate\Support\Str;

use MM\Meros\Facades\Fields;
use MM\Meros\Facades\Framework;
use MM\Meros\Facades\PostMetaDefinitions;
use MM\Meros\Facades\UserMetaDefinitions;

use MM\Meros\Services\Contracts\Forms\Field;

use MM\Meros\App\Models\Post;

class MergeFields {
    private array $mergeFields = [];

    private function __construct() {
        $this->mergeFields = [
            'user_displayname' => [
                'label' => 'User Display Name',
                'description' => 'The display name of the current user, if available.',
                'type' => 'string',
            ],
            'user_firstname' => [
                'label' => 'User First Name',
                'description' => 'The first name of the current user, if available.',
                'type' => 'string',
            ],
            'user_lastname' => [
                'label' => 'User Last Name',
                'description' => 'The last name of the current user, if available.',
                'type' => 'string',
            ],
            'user_username' => [
                'label' => 'User Username',
                'description' => 'The username of the current user, if available.',
                'type' => 'string',
            ],
            'user_email' => [
                'label' => 'User Email',
                'description' => 'The email address of the current user, if available.',
                'type' => 'string',
            ],
            'user_id' => [
                'label' => 'User ID',
                'description' => 'The unique ID of the current user, if available.',
                'type' => 'integer',
            ],
            'user_id_value' => [
                'label' => 'User ID Value',
                'description' => 'The current user ID as a string value for select-based dynamic defaults.',
                'type' => 'string',
                'picker_dynamic_sources' => ['users'],
            ],
            'user_created_date' => [
                'label' => 'User Created Date',
                'description' => 'The current user registration date, formatted for date fields.',
                'type' => 'string',
                'picker_field_types' => ['date'],
            ],
            'user_created_time' => [
                'label' => 'User Created Time',
                'description' => 'The current user registration time, formatted for time fields.',
                'type' => 'string',
                'picker_field_types' => ['time'],
            ],
            'user_modified_date' => [
                'label' => 'User Modified Date',
                'description' => 'The current user modified date, if available, formatted for date fields.',
                'type' => 'string',
                'picker_field_types' => ['date'],
            ],
            'user_modified_time' => [
                'label' => 'User Modified Time',
                'description' => 'The current user modified time, if available, formatted for time fields.',
                'type' => 'string',
                'picker_field_types' => ['time'],
            ],
            'post_id' => [
                'label' => 'Post ID',
                'description' => 'The unique ID of the current post, if available.',
                'type' => 'integer',
            ],
            'post_id_value' => [
                'label' => 'Post ID Value',
                'description' => 'The current post ID as a string value for select-based dynamic defaults.',
                'type' => 'string',
                'picker_dynamic_sources' => ['posts'],
            ],
            'post_title' => [
                'label' => 'Post Title',
                'description' => 'The title of the current post, if available.',
                'type' => 'string',
            ],
            'post_content' => [
                'label' => 'Post Content',
                'description' => 'The content of the current post, if available.',
                'type' => 'string',
            ],
            'post_created_date' => [
                'label' => 'Post Created Date',
                'description' => 'The current post created date, formatted for date fields.',
                'type' => 'string',
                'picker_field_types' => ['date'],
            ],
            'post_created_time' => [
                'label' => 'Post Created Time',
                'description' => 'The current post created time, formatted for time fields.',
                'type' => 'string',
                'picker_field_types' => ['time'],
            ],
            'post_modified_date' => [
                'label' => 'Post Modified Date',
                'description' => 'The current post modified date, formatted for date fields.',
                'type' => 'string',
                'picker_field_types' => ['date'],
            ],
            'post_modified_time' => [
                'label' => 'Post Modified Time',
                'description' => 'The current post modified time, formatted for time fields.',
                'type' => 'string',
                'picker_field_types' => ['time'],
            ],
            'today' => [
                'label' => 'Today',
                'description' => 'The current date in site-local time, formatted for date fields.',
                'type' => 'string',
                'picker_field_types' => ['date'],
            ],
            'now' => [
                'label' => 'Now',
                'description' => 'The current time in site-local time, formatted for time fields.',
                'type' => 'string',
                'picker_field_types' => ['time'],
            ],
        ];
    }

    public static function get(?string $field = null, array $additionalFields = []): self|array|null {
        $instance = new self();

        $params = func_get_args();
        if (count($params) === 1 && is_array($params[0])) {
            $additionalFields = $params[0];
        }

        if ($additionalFields) {
            $instance->mergeFields = array_merge($instance->mergeFields, $additionalFields);
        }

        if ($field) {
            return $instance->mergeFields[$field] ?? null;
        }

        return $instance;
    }

    public static function merge(array $data): self {
        $instance = new self();
        $instance->mergeFields = array_merge($instance->mergeFields, $data);
        return $instance;
    }

    public function toOptions(?string $dataType = null, ?string $fieldType = null, ?string $dynamicOptionsSource = null): array {
        return collect($this->mergeFields)
            ->filter(fn($field, $key) => $this->isCompatibleWithPickerContext($key, $field, $dataType, $fieldType, $dynamicOptionsSource))
            ->mapWithKeys(fn($field, $key) => [$key => $field['label']])
            ->toArray();
    }

    public function toArray(): array {
        return $this->mergeFields;
    }

    public function toField(
        string $dataType,
        ?string $fieldType = null,
        ?string $dynamicOptionsSource = null,
        ?string $id = null, 
        ?string $name = null, 
        ?string $label = null,
        ?string $helpText = null
    ): Field {
        return Fields::checkout(Framework::get())
            ->makeFrom('advanced_select', function($field) use ($dataType, $fieldType, $dynamicOptionsSource, $id, $name, $label, $helpText) {
                if ($id !== null) {
                    $field->id($id);
                }

                if ($name !== null) {
                    $field->name($name);
                }

                if ($label !== null) {
                    $field->label($label);
                }

                if ($helpText !== null) {
                    $field->helpText($helpText);
                }

                $field->helpText('Select a merge field to insert into the content.')
                    ->options($this->toOptions($dataType, $fieldType, $dynamicOptionsSource));
        });
    }

    private function isCompatibleWithPickerContext(string $key, array $field, ?string $dataType, ?string $fieldType, ?string $dynamicOptionsSource): bool {
        if (!$this->isCompatibleWithDataType($field, $dataType)) {
            return false;
        }

        if (!$this->isCompatibleWithFieldType($field, $fieldType)) {
            return false;
        }

        return $this->isCompatibleWithDynamicOptionsSource($field, $dynamicOptionsSource);
    }

    private function isCompatibleWithDataType(array $field, ?string $dataType): bool {
        if ($dataType === null || trim($dataType) === '') {
            return true;
        }

        $fieldType = $field['type'] ?? null;
        if (!is_string($fieldType) || $fieldType === '') {
            return false;
        }

        $normalizedDataType = strtolower(trim($dataType));
        $normalizedFieldType = strtolower(trim($fieldType));

        if ($normalizedFieldType === $normalizedDataType) {
            return true;
        }

        return Str::contains($normalizedFieldType, '.')
            && Str::before($normalizedFieldType, '.') === $normalizedDataType;
    }

    private function isCompatibleWithFieldType(array $field, ?string $fieldType): bool {
        if ($fieldType === null || trim($fieldType) === '') {
            return true;
        }

        $pickerFieldTypes = $field['picker_field_types'] ?? null;

        if (!is_array($pickerFieldTypes) || $pickerFieldTypes === []) {
            return !in_array(strtolower(trim($fieldType)), ['date', 'time'], true);
        }

        return in_array(strtolower(trim($fieldType)), array_map(fn($type) => strtolower((string) $type), $pickerFieldTypes), true);
    }

    private function isCompatibleWithDynamicOptionsSource(array $field, ?string $dynamicOptionsSource): bool {
        if ($dynamicOptionsSource === null || trim($dynamicOptionsSource) === '') {
            return true;
        }

        $pickerDynamicSources = $field['picker_dynamic_sources'] ?? null;
        if (!is_array($pickerDynamicSources) || $pickerDynamicSources === []) {
            return false;
        }

        return in_array(strtolower(trim($dynamicOptionsSource)), array_map(fn($source) => strtolower((string) $source), $pickerDynamicSources), true);
    }

    public function resolve(string $key, string $requiredDataType): mixed {
        $field = $this->mergeFields[$key] ?? null;

        if (in_array($key, ['today', 'now'], true)) {
            return $this->validateResolvedValue($this->resolveDateTimeField($key), $requiredDataType);
        }

        if (Str::startsWith($key, 'user_')) {
            return $this->validateResolvedValue($this->resolveUserField($key), $requiredDataType);
        }

        if (Str::startsWith($key, 'post_')) {
            return $this->validateResolvedValue($this->resolvePostField($key), $requiredDataType);
        }

        if ($field !== null) {
            if (isset($field['value'])) {
                return $this->validateResolvedValue($field['value'], $requiredDataType);
            }
        }

        // Fallback: allow direct keys (e.g. "my_custom_meta_key") without pre-registration.
        // Prefer post context first, then current user.
        $postMetaValue = $this->resolvePostMetaField($key);
        if ($postMetaValue !== null) {
            return $this->validateResolvedValue($postMetaValue, $requiredDataType);
        }

        return $this->validateResolvedValue($this->resolveUserMetaField($key), $requiredDataType);
    }

    private function resolveDateTimeField(string $key): ?string {
        return match ($key) {
            'today' => current_time('Y-m-d'),
            'now' => current_time('H:i'),
            default => null,
        };
    }

    private function validateResolvedValue(mixed $value, string $requiredDataType): mixed {
        if ($value === null) {
            return null;
        }

        $type = strtolower(trim($requiredDataType));

        if ($type === '') {
            return $value;
        }

        return match ($type) {
            'string'          => is_string($value) ? $value : null,
            'integer', 'int'  => is_int($value) ? $value : null,
            'boolean', 'bool' => is_bool($value) ? $value : null,
            'float', 'double' => is_float($value) ? $value : null,
            'array'           => is_array($value) ? $value : null,
            'array.scalar'    => $this->isScalarArray($value) ? $value : null,
            'array.object'    => $this->isObjectArray($value) ? $value : null,
            default => null,
        };
    }

    private function isScalarArray(mixed $value): bool {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_scalar($item)) {
                return false;
            }
        }

        return true;
    }

    private function isObjectArray(mixed $value): bool {
        if (!is_array($value)) {
            return false;
        }

        foreach (array_keys($value) as $key) {
            if (is_string($key)) {
                return true;
            }
        }

        return false;
    }

    private function resolveUserField(string $key): mixed {
        $user = wp_get_current_user();

        if (!$user || !$user->exists()) {
            return null;
        }

        $firstName = $user->first_name ?: get_user_meta($user->ID, 'first_name', true);
        $lastName = $user->last_name ?: get_user_meta($user->ID, 'last_name', true);

        if (Str::startsWith($key, 'user_meta_')) {
            return $this->resolveUserMetaField(Str::after($key, 'user_meta_'), $user->ID);
        }

        return match ($key) {
            'user_displayname' => $user->display_name,
            'user_firstname'   => $firstName ?: null,
            'user_lastname'    => $lastName ?: null,
            'user_username'    => $user->user_login,
            'user_email'       => $user->user_email,
            'user_id'          => $user->ID,
            'user_id_value'    => (string) $user->ID,
            'user_created_date' => $this->formatDateTimeValue($user->user_registered, 'Y-m-d'),
            'user_created_time' => $this->formatDateTimeValue($user->user_registered, 'H:i'),
            'user_modified_date' => $this->resolveUserModifiedDateTime($user->ID, 'Y-m-d'),
            'user_modified_time' => $this->resolveUserModifiedDateTime($user->ID, 'H:i'),
            default => null,
        };
    }

    private function resolvePostField(string $key): mixed {
        $post = get_post();

        if (!$post instanceof \WP_Post) {
            return null;
        }

        if (Str::startsWith($key, 'post_meta_')) {
            return $this->resolvePostMetaField(Str::after($key, 'post_meta_'));
        }

        return match ($key) {
            'post_id' => $post->ID,
            'post_id_value' => (string) $post->ID,
            'post_title' => $post->post_title,
            'post_content' => $post->post_content,
            'post_created_date' => $this->formatPostDateTime($post, 'date', 'Y-m-d'),
            'post_created_time' => $this->formatPostDateTime($post, 'date', 'H:i'),
            'post_modified_date' => $this->formatPostDateTime($post, 'modified', 'Y-m-d'),
            'post_modified_time' => $this->formatPostDateTime($post, 'modified', 'H:i'),
            default => null,
        };
    }

    private function resolveUserModifiedDateTime(int $userId, string $format): ?string {
        foreach (['last_update', 'last_updated', 'profile_last_updated', 'modified'] as $metaKey) {
            $value = get_user_meta($userId, $metaKey, true);

            if ($value !== '') {
                return $this->formatDateTimeValue($value, $format);
            }
        }

        return null;
    }

    private function resolveUserMetaField(string $metaKey, ?int $userId = null, bool $single = true): mixed {
        if ($metaKey === '') {
            return null;
        }

        $resolvedUserId = $userId;

        if ($resolvedUserId === null) {
            $user = wp_get_current_user();
            if (!$user || !$user->exists()) {
                return null;
            }

            $resolvedUserId = (int)$user->ID;
        }

        $registeredMetaValue = $this->resolveRegisteredUserMetaField($resolvedUserId, $metaKey);
        if ($registeredMetaValue !== null) {
            return $registeredMetaValue;
        }

        $value = get_user_meta($resolvedUserId, $metaKey, $single);

        if ($single && $value === '') {
            return null;
        }

        if (!$single && is_array($value)) {
            return array_map(fn($metaValue) => $this->normalizeMetaValue($metaValue), $value);
        }

        return $this->normalizeMetaValue($value);
    }

    private function resolveRegisteredUserMetaField(int $userId, string $metaKey): mixed {
        $merosRegisteredMeta = UserMetaDefinitions::get($metaKey);
        if (!$merosRegisteredMeta) {
            return null;
        }

        $metaPath = $merosRegisteredMeta->getPath();
        $pathSegments = explode('.', $metaPath);
        $rootSegment = $pathSegments[0] ?? null;

        if (!is_string($rootSegment) || $rootSegment === '') {
            return $merosRegisteredMeta->getDefault();
        }

        if ($rootSegment === $metaKey) {
            $rootValue = get_user_meta($userId, $metaKey, true);

            if ($rootValue !== '') {
                return $this->normalizeMetaValue($rootValue);
            }

            return $merosRegisteredMeta->getDefault();
        }

        if (in_array($metaKey, $pathSegments, true)) {
            $parentValue = get_user_meta($userId, $rootSegment, true);
            $parentValue = $this->normalizeMetaValue($parentValue);

            if (is_array($parentValue) && array_key_exists($metaKey, $parentValue)) {
                return $parentValue[$metaKey];
            }
        }

        return $merosRegisteredMeta->getDefault();
    }

    private function resolvePostMetaField(string $metaKey, bool $single = true): mixed {
        if ($metaKey === '') {
            return null;
        }

        $post = $this->getCurrentPostModel();
        if (!$post) {
            return null;
        }

        if ($single) {
            return $this->resolveSinglePostMetaField($post, $metaKey);
        }

        return $this->resolveMultiplePostMetaFields($post, $metaKey);
    }

    private function getCurrentPostModel(): ?Post {
        $wpPost = get_post();

        if (!$wpPost instanceof \WP_Post) {
            return null;
        }

        return Post::query()->find((int)$wpPost->ID);
    }

    private function resolveSinglePostMetaField(Post $post, string $metaKey): mixed {
        $meta = $this->findPostMetaRecord($post, $metaKey);
        if ($meta) {
            return $this->normalizeMetaValue($meta->meta_value);
        }

        $registeredMetaValue = $this->resolveRegisteredPostMetaField($post, $metaKey);
        if ($registeredMetaValue !== null) {
            return $registeredMetaValue;
        }

        return $this->resolveWpPostMetaFallback($post, $metaKey);
    }

    private function resolveMultiplePostMetaFields(Post $post, string $metaKey): mixed {
        $values = $post->meta()
            ->where('meta_key', $metaKey)
            ->orderBy('meta_id')
            ->pluck('meta_value')
            ->map(fn($value) => $this->normalizeMetaValue($value))
            ->values()
            ->toArray();

        if ($values === []) {
            return null;
        }

        return $values;
    }

    private function findPostMetaRecord(Post $post, string $metaKey): mixed {
        return $post->meta()
            ->where('meta_key', $metaKey)
            ->orderBy('meta_id')
            ->first();
    }

    private function resolveRegisteredPostMetaField(Post $post, string $metaKey): mixed {
        $merosRegisteredMeta = PostMetaDefinitions::get($metaKey);
        if (!$merosRegisteredMeta) {
            return null;
        }

        $metaPath = $merosRegisteredMeta->getPath();
        $pathSegments = explode('.', $metaPath);
        $rootSegment = $pathSegments[0] ?? null;

        if ($rootSegment === $metaKey) {
            return $merosRegisteredMeta->getDefault();
        }

        if (in_array($metaKey, $pathSegments, true)) {
            $parentMeta = $this->findPostMetaRecord($post, (string)$rootSegment);

            if ($parentMeta) {
                $parentValue = $this->normalizeMetaValue($parentMeta->meta_value);

                if (is_array($parentValue) && array_key_exists($metaKey, $parentValue)) {
                    return $parentValue[$metaKey];
                }
            }
        }

        // If the meta is registered but not found in the db, return its default value.
        return $merosRegisteredMeta->getDefault();
    }

    private function resolveWpPostMetaFallback(Post $post, string $metaKey): mixed {
        $wpMetaValue = get_post_meta($post->ID, $metaKey, true);
        if ($wpMetaValue !== '') {
            return $this->normalizeMetaValue($wpMetaValue);
        }

        return null;
    }

    private function normalizeMetaValue(mixed $value): mixed {
        $value = maybe_unserialize($value);

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || !Str::startsWith($trimmed, ['{', '['])) {
            return $value;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    private function formatPostDateTime(\WP_Post $post, string $field, string $format): ?string {
        $dateTime = get_post_datetime($post, $field);

        if (!$dateTime instanceof \DateTimeInterface) {
            return null;
        }

        return wp_date($format, $dateTime->getTimestamp(), $dateTime->getTimezone());
    }

    private function formatDateTimeValue(mixed $value, string $format): ?string {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return wp_date($format, (int) $value, wp_timezone());
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return null;
        }

        return wp_date($format, $timestamp, wp_timezone());
    }
}