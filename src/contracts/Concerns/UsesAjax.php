<?php

namespace MM\Meros\Contracts\Concerns;

use Closure;
use MM\Meros\Facades\Support\Context;

trait UsesAjax {
    /**
     * The site's ajax url.
     *
     * @var string
     */
    protected string $ajaxUrl = '';

    /**
     * The nonce created in the initAjax method.
     *
     * @var string
     */
    protected string $ajaxNonce = '';

    /**
     * The specified and configured ajax action name.
     *
     * @var string
     */
    protected string $ajaxAction = '';

    /**
     * The callback fired when the ajax action is called.
     *
     * @var Closure|null
     */
    protected ?Closure $ajaxCallback = null;

    /**
     * Indicates that the item's ajax action has been initialised.
     *
     * @var boolean
     */
    private bool $ajaxInitialised = false;

    /**
     * Creates a Wordpress ajax action using the specified action and callback
     * Callbacks may also be set directly using the ajaxCallback property.
     *
     * @param string       $action
     * @param Closure|null $callback
     *
     * @return void
     */
    protected function initAjax(string $action, ?Closure $callback = null): void {
        $this->ajaxUrl    = admin_url('admin-ajax.php');
        $this->ajaxAction = Context::getAjaxHook($action);
        $this->ajaxNonce  = wp_create_nonce($this->ajaxAction);
        
        if ($this->ajaxCallback === null && $callback !== null) {
            $this->ajaxCallback = $callback;
        }

        if ($this->ajaxCallback === null) {
            return;
        }

        $this->addAjaxAction();
        $this->ajaxInitialised = true;
    }

    /**
     * Resets the currently set ajax configuration with a new action name and optional callback.
     *
     * @param string       $action
     * @param Closure|null $callback
     *
     * @return void
     */
    protected function reinitAjax(string $action, ?Closure $callback = null): void {
        if ($this->ajaxInitialised) {
            $this->removeAjax();
        }

        $this->initAjax($action, $callback);
    }

    /**
     * Removes the previously set ajax action.
     *
     * @return void
     */
    protected function removeAjax(): void {
        remove_action($this->ajaxAction, $this->ajaxCallback);
        $this->ajaxUrl = '';
        $this->ajaxNonce = '';
        $this->ajaxAction = '';
        $this->ajaxInitialised = false;
    }

    /**
     * Adds the configured ajax action to Wordpress.
     *
     * @return void
     */
    private function addAjaxAction(): void {
         add_action($this->ajaxAction, function () {
            if (!check_ajax_referer($this->ajaxAction, 'nonce', false)) {
                wp_send_json_error(['message' => 'Invalid request.'], 403);
                exit;
            }

            call_user_func($this->ajaxCallback, $_POST);
            exit;
        });
    }

    /**
     * Retrieves the AJAX URL for fetching the edit form of a repeater row.
     *
     * @return string
     */
    public function getAjaxUrl(): string {
        return $this->ajaxUrl;
    }

    /**
     * Retrieves the AJAX nonce for fetching the edit form of a repeater row.
     *
     * @return string
     */
    public function getAjaxNonce(): string {
        return $this->ajaxNonce;
    }
}