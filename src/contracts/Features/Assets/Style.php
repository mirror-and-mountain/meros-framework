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
        } else {
            $this->__registerAsset();
            wp_enqueue_style($this->handle);
        }

        $this->isEnqueued = true;
    }

    final public function register(): static {
        if (!$this->preRegistered) {
            add_action($this->resolveRegisterHook(), [$this, '__registerAsset']);
            $this->preRegistered = true;
        }
        return $this;
    }

    final public function enqueue(): static {
        if (!$this->preEnqueued) {
            add_action($this->resolveEnqueueHook(), [$this, '__enqueueAsset']);
            $this->preEnqueued = true;
        }
        return $this;
    }
}