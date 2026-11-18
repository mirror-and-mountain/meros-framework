<?php 

namespace MM\Meros\Support;

class FormDynamicValues {
    private array $userData = [];
    private array $postData = [];

    public function __construct() {
        $this->setUserData();
    }

    public function get(): array {
        $this->setPostData();
        
        return apply_filters('meros_dynamic_form_values', [
            'user_values' => $this->userData,
            'post_values' => $this->postData
        ]);
    }

    /**
     * Resolves the value for the given key from the dynamic form values.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function resolve(string $key): mixed {
        $key = str_replace(['{{', '}}'], '', $key);
        $values = $this->get();

        if (array_key_exists($key, $values['user_values'])) {
            return $values['user_values'][$key];
        }

        if (array_key_exists($key, $values['post_values'])) {
            return $values['post_values'][$key];
        }

        // In case filters have been applied and additional groups exist, check them as well.
        foreach ($values as $groupName => $group) {
            if ($groupName === 'user_values' || $groupName === 'post_values') {
                continue;
            }

            if (array_key_exists($key, $group)) {
                return $group[$key];
            }
        }

        return null;
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
            'user_id'           => $currentUserId,
            'user_display_name' => $userExists ? $currentUser->display_name : '',
            'user_email'        => $userExists ? $currentUser->user_email : '',
            'user_first_name'   => $userExists ? $currentUser->first_name : '',
            'user_last_name'    => $userExists ? $currentUser->last_name : '',
            'user_nickname'     => $userExists ? $currentUser->nickname : ''
        ];
    }

    private function setPostData(): void {
        $currentPostId = get_the_ID();
        $currentPost   = $currentPostId !== false ? get_post($currentPostId) : null;
        $postExists    = $currentPost instanceof \WP_Post;

        $this->postData = [
            'post_id'    => $currentPostId,
            'post_title' => $postExists ? $currentPost->post_title : '',
            'post_slug'  => $postExists ? $currentPost->post_name : ''
        ];
    }
}