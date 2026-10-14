<?php

namespace MM\Meros\Support;

use Closure;
use MM\Meros\Facades\Support\Context;

class Ajax {
    private array $actions;

    public function __construct() {
        // Do nothing...
    }

    public function addAction(string $action, Closure $callback, array $callbackArgs = [], bool $allowGuests = false): string {
        $hook = Context::getAjaxHook($action);
        $nonce = wp_create_nonce($action);

        $handler = function () use ($action, $callback, $callbackArgs) {
            if (!check_ajax_referer($action, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid request.'], 403);
                exit;
            }

            $request = array_merge($_GET, $_POST);
            $args = [];

            foreach ($callbackArgs as $arg => $default) {
                if (isset($request[$arg])) {
                    $args[$arg] = $request[$arg];
                } else {
                    $args[$arg] = $default;
                }
            }

            call_user_func($callback, empty($args) ? $request : $args);
        };

        add_action($hook, $handler);

        if ($allowGuests) {
            add_action('wp_ajax_nopriv_' . $action, $handler);
        }

        $this->actions[$action] = [
            'hook'     => $hook,
            'nonce'    => $nonce,
            'callback' => $callback
        ];

        return $nonce;
    }

    public function getAction(string $action): ?array {
        return $this->actions[$action] ?? null;
    }

    public function getActionNonce(string $action): ?string {
        if (isset($this->actions[$action]) && isset($this->actions[$action]['nonce'])) {
            return $this->actions[$action]['nonce'];
        }

        return null;
    }

    public function getUrl(): string {
        return admin_url('admin-ajax.php');
    }

    public function get(): self {
        return $this;
    }
}