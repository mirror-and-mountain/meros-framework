<?php 

namespace MM\Meros\Support;

class FormDynamicValues {
    public const TYPES = ['string', 'integer', 'number', 'boolean', 'array', 'array.scalar', 'array.object', 'null'];

    private array $userData = [];
    private array $postData = [];

    public function __construct() {
        $this->setUserData();
    }

    public function get(): array {
        $this->setPostData();
        
        $values = apply_filters('meros_dynamic_form_values', [
            'user_values' => $this->userData,
            'post_values' => $this->postData
        ]);
        if (!is_array($values)) {
            throw new \UnexpectedValueException('Dynamic form values must be grouped arrays of typed definitions.');
        }
        foreach ($values as $groupName => $group) {
            if (!is_array($group)) {
                throw new \UnexpectedValueException("Dynamic form value group '{$groupName}' must be an array.");
            }
            foreach ($group as $key => $definition) {
                if (!is_string($key) || !is_array($definition) ||
                    !array_key_exists('value', $definition) || !isset($definition['type']) ||
                    !in_array($definition['type'], self::TYPES, true)) {
                    throw new \UnexpectedValueException("Dynamic form value '{$groupName}.{$key}' requires a valid type and value.");
                }
                $value = $definition['value'];
                $valid = $value === null || match ($definition['type']) {
                    'number' => is_int($value) || is_float($value),
                    'array.scalar' => is_array($value) && count(array_filter($value, 'is_scalar')) === count($value),
                    'array.object' => is_array($value) && count(array_filter($value, 'is_array')) === count($value),
                    default => self::typeOf($value) === $definition['type'],
                };
                if (!$valid) {
                    throw new \UnexpectedValueException("Dynamic form value '{$groupName}.{$key}' does not match its declared type '{$definition['type']}'.");
                }
            }
        }
        return $values;
    }

    /**
     * Resolves the value for the given key from the dynamic form values.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function resolve(string $key): mixed {
        return $this->definition($key)['value'] ?? null;
    }

    public function definition(string $key): ?array {
        $key = trim(str_replace(['{{', '}}'], '', $key));
        $values = $this->get();
        foreach (['user_values' => $values['user_values'] ?? [], 'post_values' => $values['post_values'] ?? []] + $values as $group) {
            if (array_key_exists($key, $group)) {
                return $group[$key];
            }
        }
        return null;
    }

    public function choices(): array {
        $values = $this->get();
        $choices = [];
        foreach (['user_values' => $values['user_values'] ?? [], 'post_values' => $values['post_values'] ?? []] + $values as $group) {
            foreach ($group as $name => $definition) {
                $choices[$name] ??= ['name' => $name, 'type' => $definition['type']];
            }
        }
        return array_values($choices);
    }

    public static function typeOf(mixed $value): string {
        return match (gettype($value)) {
            'double' => 'number',
            'NULL' => 'null',
            'string', 'integer', 'boolean', 'array' => gettype($value),
            default => throw new \UnexpectedValueException('Dynamic form values must be scalar, arrays, or null.'),
        };
    }

    /**
     * Parses the given content and replaces dynamic value placeholders with their resolved values.
     *
     * @param string $content
     * @param callable|null $formatValue Formats a resolved value for the output context.
     *
     * @return string
     */
    public function parse(string $content, ?callable $formatValue = null): string {
        return preg_replace_callback('/{{\s*([^}]+)\s*}}/', function ($matches) use ($formatValue) {
            $value = $this->resolve(trim($matches[1]));
            return $formatValue !== null ? $formatValue($value) : (is_scalar($value) ? (string) $value : '');
        }, $content);
    }

    /**
     * Parses the given HTML content and replaces dynamic value placeholders with their resolved values, escaping them for safe HTML output.
     *
     * @param string $content
     *
     * @return string
     */
    public function parseHtml(string $content): string {
        $formatValue = static function (mixed $value): string {
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }
            return esc_html(is_scalar($value) ? (string) $value : '');
        };

        // Interpolate text nodes only. In particular, never substitute into link URLs.
        $parts = wp_html_split(wp_kses_post($content));
        for ($i = 0, $count = count($parts); $i < $count; $i += 2) {
            $parts[$i] = $this->parse($parts[$i], $formatValue);
        }
        return implode('', $parts);
    }

    /**
     * Sets the current user data for dynamic value resolution.
     *
     * @return void
     */
    private function setUserData(): void {
        $currentUserId = get_current_user_id();
        $currentUser   = get_user_by('ID', $currentUserId);
        $userExists    = $currentUser instanceof \WP_User;

        $this->userData = [
            'user_id'           => ['type' => 'integer', 'value' => $currentUserId],
            'user_display_name' => ['type' => 'string', 'value' => $userExists ? $currentUser->display_name : ''],
            'user_email'        => ['type' => 'string', 'value' => $userExists ? $currentUser->user_email : ''],
            'user_first_name'   => ['type' => 'string', 'value' => $userExists ? $currentUser->first_name : ''],
            'user_last_name'    => ['type' => 'string', 'value' => $userExists ? $currentUser->last_name : ''],
            'user_nickname'     => ['type' => 'string', 'value' => $userExists ? $currentUser->nickname : '']
        ];
    }

    /**
     * Sets the current post data for dynamic value resolution.
     *
     * @return void
     */
    private function setPostData(): void {
        $currentPostId = get_the_ID();
        $currentPost   = $currentPostId !== false ? get_post($currentPostId) : null;
        $postExists    = $currentPost instanceof \WP_Post;

        $this->postData = [
            'post_id'    => ['type' => 'integer', 'value' => $currentPostId === false ? null : $currentPostId],
            'post_title' => ['type' => 'string', 'value' => $postExists ? $currentPost->post_title : ''],
            'post_slug'  => ['type' => 'string', 'value' => $postExists ? $currentPost->post_name : '']
        ];
    }
}