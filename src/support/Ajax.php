<?php

namespace MM\Meros\Support;

use Closure;
use MM\Meros\Facades\Support\Context;

class Ajax {
    private array $actions;

    public function __construct() {
        // Do nothing...
    }

    /**
     * Returns the ajax hook prefix based on the current context (admin or site).
     *
     * @param boolean $private
     *
     * @return string
     */
    public function getAjaxHook(string $hook, bool $private = false): string {
        return Context::isAdmin() 
            ? 'wp_ajax_' . $hook 
            : ($private === false ? 'wp_ajax_nopriv_' . $hook : 'wp_ajax_' . $hook);
    }

    /**
     * Adds an AJAX action handler for the specified action.
     *
     * @param string  $action
     * @param Closure $callback
     * @param array   $callbackArgs
     * @param boolean $allowGuests
     *
     * @return string
     */
    public function addAction(string $action, Closure $callback, array $callbackArgs = [], bool $allowGuests = false): string {
        $hook = $this->getAjaxHook($action);
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

    /**
     * Retrieves the details of a previously added AJAX action handler.
     *
     * @param string $action
     *
     * @return array|null
     */
    public function getAction(string $action): ?array {
        return $this->actions[$action] ?? null;
    }

    /**
     * Retrieves the nonce associated with a previously added AJAX action handler.
     *
     * @param string $action
     *
     * @return string|null
     */
    public function getActionNonce(string $action): ?string {
        if (isset($this->actions[$action]) && isset($this->actions[$action]['nonce'])) {
            return $this->actions[$action]['nonce'];
        }

        return null;
    }

    /**
     * Retrieves the URL for the admin AJAX handler.
     *
     * @return string
     */
    public function getUrl(): string {
        return admin_url('admin-ajax.php');
    }

    /**
     * Retrieves the current instance of the Ajax support class.
     *
     * @return self
     */
    public function get(): self {
        return $this;
    }
}