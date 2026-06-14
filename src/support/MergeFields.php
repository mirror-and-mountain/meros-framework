<?php 

namespace MM\Meros\Support;

class MergeFields {
    private static array $mergeFields = [
        'user_displayname' => [
            'label' => 'User Display Name',
            'description' => 'The display name of the current user, if available.',
        ],
        'user_firstname' => [
            'label' => 'User First Name',
            'description' => 'The first name of the current user, if available.',
        ],
        'user_lastname' => [
            'label' => 'User Last Name',
            'description' => 'The last name of the current user, if available.',
        ],
        'user_username' => [
            'label' => 'User Username',
            'description' => 'The username of the current user, if available.',
        ],
        'user_email' => [
            'label' => 'User Email',
            'description' => 'The email address of the current user, if available.',
        ],
        'user_id' => [
            'label' => 'User ID',
            'description' => 'The unique ID of the current user, if available.',
        ],
        'post_id' => [
            'label' => 'Post ID',
            'description' => 'The unique ID of the current post, if available.',
        ],
        'post_title' => [
            'label' => 'Post Title',
            'description' => 'The title of the current post, if available.',
        ],
        'post_content' => [
            'label' => 'Post Content',
            'description' => 'The content of the current post, if available.',
        ],
    ];

    public static function getMergeFields(bool $asOptionsList = false): array {
        if ($asOptionsList) {
            return collect(self::$mergeFields)
                ->mapWithKeys(fn($field, $key) => [$key => $field['label']])
                ->toArray();
        }

        return self::$mergeFields;
    }

    public static function getMergeField(string $field): ?array {
        return self::$mergeFields[$field] ?? null;
    }

    public static function resolveMergeField
}