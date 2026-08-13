<?php

namespace MM\Meros\Services\Controllers;

use MM\Meros\Services\Contracts\Forms\DynamicChoiceSource;

use MM\Meros\Facades\Framework;
use MM\Meros\Facades\DynamicChoiceSources;

use Illuminate\Support\Facades\Log;

class RestController {
    /**
     * Registers REST API routes for the framework.
     *
     * @return void
     */
    public function registerRoutes($framework = null): void {
        $this->registerBladeViewRoute();
        $this->registerDynamicOptionsRoute();
    }

    /**
     * Registers a REST API route for rendering Blade views.
     *
     * @return void
     */
    private function registerBladeViewRoute(): void {
        add_action('rest_api_init', function () {
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
     * Registers a REST API route for resolving dynamic choice source options.
     *
     * @return void
     */
    private function registerDynamicOptionsRoute(): void {
        add_action('rest_api_init', function () {
            register_rest_route('meros/v1', '/dynamic-options', [
                'methods'             => [\WP_REST_Server::READABLE],
                'permission_callback' => function () {
                    return true;
                },
                'callback' => function (\WP_REST_Request $request) {
                    $source = sanitize_text_field($request->get_param('source'));

                    if (!$source || empty($source)) {
                        return rest_ensure_response([
                            'error'   => 'Missing dynamic choice source',
                            'options' => []
                        ]);
                    }

                    $sourceInstance = DynamicChoiceSources::checkout(Framework::get())->get($source);

                    if (!$sourceInstance || !$sourceInstance instanceof DynamicChoiceSource) {
                        return rest_ensure_response([
                            'error'   => 'Invalid dynamic choice source',
                            'options' => []
                        ]);
                    }

                    try {
                        $options = $sourceInstance->resolve($request);

                        if ($options instanceof \WP_Error) {
                            return rest_ensure_response([
                                'error'   => $options->get_error_message(),
                                'options' => []
                            ]);
                        }
                    } catch (\Exception $e) {
                        return rest_ensure_response([
                            'error'   => 'Error resolving dynamic options: ' . $e->getMessage(),
                            'options' => []
                        ]);
                    }

                    $sorted = collect($options)->sortBy('text')->values()->all();

                    return rest_ensure_response(['options' => $sorted]);
                },
            ]);
        });
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
