<?php

namespace MM\Meros\Contracts\Concerns;

use Closure;
use MM\Meros\Facades\Support\Context;

// Here - reworking to allow for multiple actions per class.

trait UsesAjax {
    /**
     * The site's ajax url.
     *
     * @var string
     */
    private string $ajaxUrl = '';

    /**
     * An array of callbacks keyed by their ajax action name
     *
     * @var array<array>
     */
    private array $ajaxActions = [];

    /**
     * Creates a Wordpress ajax action using the specified action and callback
     * Callbacks may also be set directly using the ajaxCallback property.
     *
     * @param string  $action
     * @param Closure $callback
     *
     * @return void
     */
    protected function initAjax(string $action, Closure $callback): void {
        $this->ajaxUrl = admin_url('admin-ajax.php');

        if (!in_array($action, array_keys($this->ajaxActions))) {
            $fullAction = Context::getAjaxHook($action);

            $this->ajaxActions[$action] = [
                'action'   => $fullAction,
                'nonce'    => wp_create_nonce($fullAction),
                'callback' => $callback,
            ];

            if (is_callable($callback)) {
                $registeredCallback = function () use ($fullAction, $callback) {
                    if (!check_ajax_referer($fullAction, 'nonce', false)) {
                        wp_send_json_error(['message' => 'Invalid request.'], 403);
                        exit;
                    }

                    call_user_func($callback, $_POST);
                    exit;
                };

                $this->ajaxActions[$action]['registeredCallback'] = $registeredCallback;
                add_action($fullAction, $registeredCallback);
            }
        }
    }

    /**
     * Removes the given ajax action if it exists.
     *
     * @param string $action
     *
     * @return void
     */
    protected function removeAjax(string $action): void {
        $actionData = $this->ajaxActions[$action] ?? null;

        if (!is_array($actionData)) {
            return;
        }

        if (array_key_exists('action', $actionData) &&
            array_key_exists('registeredCallback', $actionData) &&
            is_callable($actionData['registeredCallback'])
        ) {
            remove_action($actionData['action'], $actionData['registeredCallback']);
        }

        unset($this->ajaxActions[$action]);
    }

    /**
     * Retrieves the AJAX URL.
     *
     * @return string
     */
    public function getAjaxUrl(): string {
        return $this->ajaxUrl;
    }

    /**
     * Retrieves the AJAX nonce for a given action.
        *
     * @param string $action
     *
     * @return string
     */
    public function getAjaxNonce(string $action = ''): string {
        if ($action === '' && !empty($this->ajaxActions)) {
            $action = reset($this->ajaxActions);
        } else if ($action !== '') {
            $action = $this->ajaxActions[$action] ?? null;
        }

        if (!is_array($action)) {
            return '';
        }

        if (array_key_exists('nonce', $action)) {
            return $action['nonce'];
        }

        return '';
    }
}