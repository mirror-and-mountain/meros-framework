<?php 

namespace MM\Meros\Services\Contracts;

use Closure;
use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Contracts\Interfaces\DataRegistrant;
use MM\Meros\Services\Contracts\Interfaces\AdminFieldRegistrant;

use MM\Meros\Services\Concerns\IsDataRegistrant;

class PostMeta extends FeatureDefinition implements DataRegistrant, AdminFieldRegistrant {
    
    /**
     * The meta key for the post meta.
     *
     * @var string
     */
    public string $key = '';

    /**
     * The post type that this meta belongs to.
     *
     * @var string
     */
    protected string $postType = '';

    use IsDataRegistrant;

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

        $this->queue();
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

    protected function queue(): void {
        if (empty($this->postType) || empty($this->key)) {
            return;
        }


        if ($this->queued) {
            return;
        }


        add_action('init', function () {
            register_post_meta(
                $this->postType,
                $this->key,
                $this->args
            );
        });

        $this->queued = true;
    }

    /**
     * Queues the post meta for registration based on a given post type and meta key.
     * 
     * Should be called by concrete PostType instances when they register their post type.
     *
     * @param PostType $postType The post type that this meta belongs to.
     * @param string   $key      The meta key for the post meta.
     * @param array    $args     Optional additional arguments for register_post_meta.
     *
     * @return void
     */
    final public function queueFromPostType(PostType $postType, string $key, array $args = []): void {
        $this->postType = $postType->handle;
        $this->key      = $key;
        $this->args     = array_merge($this->args, $args);

        $this->queue();
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

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the authentication callback for the post meta.
     *
     * @param callable|Closure $callback A callable or method reference for authenticating access to the post meta.
     *
     * @return self
     */
    public function authCallback(callable|Closure $callback): self {
        $this->args['auth_callback'] = $this->convertToClosure($callback);
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

    /***************************
     * Getters
     ***************************/

    /**
     * Retrieves the value of the post meta for a given post ID. If no post ID is provided, it will attempt to use the global $post.
     *
     * @param string|int $postId Optional post ID to retrieve the meta for. If not provided, uses the global $post.
     *
     * @return mixed The value of the post meta, or null if not found or if required properties are missing.
     */
    final public function getValue(string $postId = ''): mixed {
        // If required properties are missing, return null
        if (empty($this->postType) || empty($this->key)) {
            return null;
        }

        // If no post ID is provided, attempt to use the global $post
        if ($postId === '') {
            global $post;
            $postId = $post->ID ?? '';
        }

        // If we still don't have a post ID, return null
        if (empty($postId)) {
            return null;
        }

        // Traverse to the root PostMeta if this is a nested structure
        $root = $this;

        // Traverse up to the root of the post meta structure
        while ($root->parent !== null) {
            $root = $root->parent;
        }

        // If the root post type doesn't match, return null
        if ($root->name !== $this->postType) {
            return null;
        }

        // Retrieve the post meta value using get_post_meta
        $value = get_post_meta($postId, $this->key, $this->args['single']);
        
        // If this is the root, return directly
        if ($this === $root) {
            return $value ?? $this->args['default']; // Return default if meta not found
        }

        // Traverse into nested structure using path
        $segments = explode('.', $this->path);

        // Remove the root segment
        array_shift($segments);

        foreach ($segments as $segment) {
            if ($segment === '*') {
                // For repeaters, return full array (handled elsewhere per index)
                return $value;
            }

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $this->args['default'] ?? null;
            }

            $value = $value[$segment];
        }

        return $value ?? $this->args['default']; // Return default if final value is null
    }

    /**
     * Retrieves the default value of the post meta.
     *
     * @return mixed
     */
    public function getDefault(): mixed {
        return $this->args['default'] ?? null;
    }

    /***************************
     * Helpers
     ***************************/
}