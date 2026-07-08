<?php

namespace MM\Meros\Services\Controllers;

use Illuminate\Support\Str;

use MM\Meros\App\Framework;

class RestController {
    /**
     * Registers REST API routes for the framework.
     *
     * @param Framework $framework
     * @return void
     */
    public function registerRoutes(Framework $framework): void {
        add_action('rest_api_init', function () use ($framework) {
            register_rest_route('meros/v1', '/get-blade-view', [
                'methods'             => [\WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
                'callback' => function (\WP_REST_Request $request) {
                    $view = sanitize_text_field($request->get_param('view'));
                    $data = $request->get_param('data');

                    $attributes = [];
                    $viewData = [];

                    if (!$view) {
                        return new \WP_Error('no_view', 'No view specified', ['status' => 400]);
                    }

                    if (is_array($data)) {
                        $attributes = $data;
                    } elseif (is_string($data) && $data !== '') {
                        $decoded = json_decode(wp_unslash($data), true);

                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                            return new \WP_Error('invalid_data', 'Invalid data payload', ['status' => 400]);
                        }

                        $attributes = $decoded;
                    }

                    $viewData = [
                        'attributes' => $attributes,
                        'data' => $attributes,
                    ];

                    foreach ($attributes as $key => $value) {
                        $viewData[$key] = $this->normaliseRestViewData($value);
                    }

                    try {
                        $rendered = view($view, $viewData)->render();

                        return rest_ensure_response(['html' => $rendered]);
                    } catch (\Exception $e) {
                        return new \WP_Error('render_error', 'Error rendering view: ' . $e->getMessage(), ['status' => 500]);
                    }
                },
            ]);

            register_rest_route('meros/v1', '/dynamic-choice-options', [
                'methods' => [\WP_REST_Server::READABLE],
                'permission_callback' => '__return_true',
                'callback' => function (\WP_REST_Request $request) use ($framework) {
                    return $this->handleDynamicChoiceOptionsRequest($framework, $request);
                },
            ]);

            add_filter('rest_pre_serve_request', function ($served, $result, $request, $server) {
                if ($request->get_route() !== '/meros/v1/get-blade-view') {
                    return $served;
                }

                if (is_wp_error($result)) {
                    return $served;
                }

                $data = $result instanceof \WP_REST_Response ? $result->get_data() : null;

                if (!is_array($data) || !isset($data['html'])) {
                    return $served;
                }

                $server->send_header('Content-Type', 'text/html; charset=' . get_option('blog_charset'));
                echo $data['html'];

                return true;
            }, 10, 4);
        });
    }

    /**
     * Handles REST requests for dynamically loaded choice field options.
     *
     * @param Framework $framework
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleDynamicChoiceOptionsRequest(Framework $framework, \WP_REST_Request $request): \WP_REST_Response {
        $source = sanitize_key((string) $request->get_param('source'));
        $options = [];

        try {
            $sourceDefinition = $source !== '' ? $framework->dynamicChoiceSource($source) : null;

            if ($sourceDefinition === null || !$sourceDefinition->isAvailable()) {
                return rest_ensure_response([
                    'options' => [],
                ]);
            }

            $resolvedOptions = $sourceDefinition->resolve($request);

            if (is_array($resolvedOptions) && array_key_exists('options', $resolvedOptions) && is_array($resolvedOptions['options'])) {
                $options = $resolvedOptions['options'];
            }

            if (is_array($resolvedOptions)) {
                $optionList = is_array($options) && $options !== [] ? $options : $resolvedOptions;

                $options = array_values(array_filter(array_map(function ($option) {
                    if (!is_array($option)) {
                        return null;
                    }

                    $value = isset($option['value']) ? trim((string) $option['value']) : '';
                    $text = isset($option['text']) ? trim((string) $option['text']) : '';

                    if ($value === '') {
                        return null;
                    }

                    return [
                        'value' => $value,
                        'text' => $text !== '' ? $text : $value,
                    ];
                }, $optionList)));
            }
        } catch (\Throwable $exception) {
            error_log(sprintf(
                '[meros] dynamic-choice-options source=%s failed: %s',
                $source,
                $exception->getMessage()
            ));
        }

        return rest_ensure_response([
            'options' => is_array($options) ? $options : [],
        ]);
    }

    /**
     * Builds dynamic choice options from a WP_Query posts lookup.
     *
     * @param \WP_REST_Request $request
     * @return array<int, array{value:string,text:string}>
     */
    public function buildDynamicPostChoiceOptions(\WP_REST_Request $request): array {
        $postType = sanitize_key((string) ($request->get_param('postType') ?: 'post'));
        if ($postType === '' || !post_type_exists($postType)) {
            return [];
        }

        $postStatus = sanitize_key((string) ($request->get_param('postStatus') ?: 'publish'));
        if (!current_user_can('edit_posts')) {
            $postStatus = 'publish';
        }

        $limit = max(1, min(100, (int) ($request->get_param('limit') ?: 20)));
        $search = sanitize_text_field((string) ($request->get_param('search') ?: ''));
        $selected = $this->normaliseDynamicChoiceSelectedValues($request->get_param('selected'));
        $taxonomy = sanitize_key((string) ($request->get_param('taxonomy') ?: ''));
        $terms = $this->normaliseDynamicChoiceTerms($request->get_param('terms'));

        $queryArgs = [
            'post_type' => $postType,
            'post_status' => $postStatus,
            'posts_per_page' => $limit,
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        ];

        if ($selected !== [] && $search === '') {
            $queryArgs['post__in'] = $selected;
            $queryArgs['orderby'] = 'post__in';
            $queryArgs['posts_per_page'] = count($selected);
        } elseif ($search !== '') {
            $queryArgs['s'] = $search;
        }

        if ($taxonomy !== '' && taxonomy_exists($taxonomy) && $terms !== []) {
            $allNumericTerms = count(array_filter($terms, fn($term) => ctype_digit((string) $term))) === count($terms);

            $queryArgs['tax_query'] = [[
                'taxonomy' => $taxonomy,
                'field' => $allNumericTerms ? 'term_id' : 'slug',
                'terms' => array_map(
                    fn($term) => $allNumericTerms ? (int) $term : sanitize_title((string) $term),
                    $terms
                ),
            ]];
        }

        $query = new \WP_Query($queryArgs);

        return array_map(function ($post) {
            $title = get_the_title($post);

            return [
                'value' => (string) $post->ID,
                'text' => $title !== '' ? html_entity_decode($title, ENT_QUOTES, get_bloginfo('charset')) : '(no title)',
            ];
        }, $query->posts);
    }

    /**
     * Builds dynamic choice options from a WP_User_Query users lookup.
     *
     * @param Framework $framework
     * @param \WP_REST_Request $request
     * @return array<int, array{value:string,text:string}>
     */
    public function buildDynamicUserChoiceOptions(Framework $framework, \WP_REST_Request $request): array {
        $limit = max(1, min(100, (int) ($request->get_param('limit') ?: 20)));
        $search = sanitize_text_field((string) ($request->get_param('search') ?: ''));
        $selected = $this->normaliseDynamicChoiceSelectedValues($request->get_param('selected'));
        $role = sanitize_key((string) ($request->get_param('userRole') ?: ''));
        $restrictToPublicUsers = $this->shouldRestrictPublicUserChoices();

        $queryArgs = [
            'number' => $limit,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => 'all',
        ];

        if ($restrictToPublicUsers && ($selected === [] || $search !== '')) {
            $queryArgs['number'] = min(100, max($limit * 3, $limit));
        }

        if ($role !== '') {
            $queryArgs['role'] = $role;
        }

        if ($selected !== [] && $search === '') {
            $queryArgs['include'] = $selected;
            $queryArgs['number'] = count($selected);
            unset($queryArgs['orderby'], $queryArgs['order']);
        } elseif ($search !== '') {
            $queryArgs['search'] = '*' . esc_attr($search) . '*';
            $queryArgs['search_columns'] = ['user_login', 'user_nicename', 'display_name', 'user_email'];
        }

        $query = new \WP_User_Query($queryArgs);
        $results = $query->get_results();

        if (!is_array($results)) {
            return [];
        }

        if ($restrictToPublicUsers) {
            $results = array_values(array_filter($results, function ($user) use ($framework) {
                return $user instanceof \WP_User && $this->isUserPubliclyQueryable($framework, (int) $user->ID);
            }));

            $results = array_slice($results, 0, $limit);
        }

        return array_map(function ($user) {
            $label = $user->display_name !== '' ? $user->display_name : $user->user_login;

            return [
                'value' => (string) $user->ID,
                'text' => html_entity_decode($label, ENT_QUOTES, get_bloginfo('charset')),
            ];
        }, $results);
    }

    /**
     * Determines whether the current request should be limited to opted-in users.
     *
     * @return bool
     */
    private function shouldRestrictPublicUserChoices(): bool {
        return !current_user_can('edit_posts');
    }

    /**
     * Returns whether the given user has opted into public querying.
     *
     * @param Framework $framework
     * @param int $userId
     * @return bool
     */
    private function isUserPubliclyQueryable(Framework $framework, int $userId): bool {
        $value = get_user_meta($userId, $this->getFrameworkUserMetaKey($framework), true);

        if (!is_array($value)) {
            return false;
        }

        return !empty($value[$this->getPubliclyQueryableUserFlagKey()]);
    }

    /**
     * Returns the root meta key used for framework-owned user profile settings.
     *
     * @param Framework $framework
     * @return string
     */
    private function getFrameworkUserMetaKey(Framework $framework): string {
        return '_' . Str::replace('-', '_', $framework->getHandle()) . '_user_meta';
    }

    /**
     * Returns the nested user meta flag used to opt users into public queries.
     *
     * @return string
     */
    private function getPubliclyQueryableUserFlagKey(): string {
        return 'publicly_queryable';
    }

    /**
     * Normalises selected dynamic option IDs from REST input.
     *
     * @param mixed $selected
     * @return array<int, int>
     */
    private function normaliseDynamicChoiceSelectedValues(mixed $selected): array {
        if (is_string($selected)) {
            $selected = array_filter(array_map('trim', explode(',', $selected)));
        }

        if (!is_array($selected)) {
            return [];
        }

        return array_values(array_filter(array_map('absint', $selected)));
    }

    /**
     * Normalises comma-separated or array term filters for dynamic options.
     *
     * @param mixed $terms
     * @return array<int, string>
     */
    private function normaliseDynamicChoiceTerms(mixed $terms): array {
        if (is_string($terms)) {
            $terms = array_filter(array_map('trim', explode(',', $terms)));
        }

        if (!is_array($terms)) {
            return [];
        }

        return array_values(array_filter(array_map(fn($term) => trim((string) $term), $terms)));
    }

    /**
     * Normalises REST view payload values for Blade rendering.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normaliseRestViewData($value) {
        if (!is_array($value)) {
            return $value;
        }

        $normalised = array_map(fn($item) => $this->normaliseRestViewData($item), $value);
        $isList = $normalised === [] || array_keys($normalised) === range(0, count($normalised) - 1);

        return $isList ? $normalised : (object) $normalised;
    }
}
