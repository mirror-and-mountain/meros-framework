<?php

namespace MM\Meros\Contracts\Features\Assets;

class Style extends Asset {
    /**
     * The area of the site where the style should be loaded. 
     * Can be either 'frontend,' 'admin' or 'editor'.
     *
     * @var string
     */
    protected string $area = 'frontend';

    // =========================================================================
    // Hooking
    // =========================================================================

    final public function __registerAsset(): void {
        wp_register_style(
            $this->handle,
            $this->src,
            $this->dependencies,
            $this->version,
        );

        $this->isRegistered = true;
    }

    final public function __enqueueAsset(): void {
        if ($this->isRegistered) {
            wp_enqueue_style($this->handle);
            $this->isEnqueued = true;
        } else {
            $this->register();
            wp_enqueue_style($this->handle);
            $this->isEnqueued = true;
        }
    }

    final public function register(): void {
        if (!$this->isRegistered) {
            add_action($this->resolveRegisterHook(), [$this, '__registerAsset']);
        }
    }

    final public function enqueue(): void {
        if (!$this->isEnqueued) {
            add_action($this->resolveEnqueueHook(), [$this, '__enqueueAsset']);
        }
    }
}