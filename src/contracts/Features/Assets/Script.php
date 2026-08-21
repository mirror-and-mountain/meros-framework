<?php

namespace MM\Meros\Contracts\Features\Assets;

class Script extends Asset {
    /**
     * The area of the site where the script should be loaded. 
     * Can be either 'frontend,' 'admin' or 'editor'.
     *
     * @var string
     */
    protected string $area = 'frontend';
    
    /**
     * Whether the script should be loaded in the footer of the page.
     *
     * @var bool
     */
    protected bool $inFooter = false;

    // =========================================================================
    // Hooking
    // =========================================================================

    final public function __registerAsset(): void {
        wp_register_script(
            $this->handle,
            $this->src,
            $this->dependencies,
            $this->version,
            $this->inFooter
        );

        $this->isRegistered = true;
    }

    final public function __enqueueAsset(): void {
        if ($this->isRegistered) {
            wp_enqueue_script($this->handle);
        } else {
            $this->__registerAsset();
            wp_enqueue_script($this->handle);
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

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets whether the script should be loaded in the footer of the page.
     *
     * @param bool $inFooter
     *
     * @return static
     */
    final public function inFooter(bool $inFooter = true): static {
        $this->inFooter = $inFooter;

        return $this;
    }
}