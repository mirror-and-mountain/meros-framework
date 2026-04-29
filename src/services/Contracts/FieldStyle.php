<?php 

namespace MM\Meros\Services\Contracts;

class FieldStyle extends FeatureDefinition {
    /**
     * A unique handle for the field style, used for registration and retrieval.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * A fully-qualified blade view path that defines the template for rendering fields with this style.
     *
     * @var string
     */
    protected string $view = '';

    /**
     * Sets the field style as ready (or not) based on its current configuration.
     *
     * @return void
     */
    protected function hook(): void {
        if (empty($this->handle) || empty($this->view)) {
            $this->ready = false;
        }

        $this->ready = true;
    }

    protected function load(): void {
        // No loading for field styles as they aren't directly hooked into WP.
    }

    /***************************
     * Public Chainable methods
     ***************************/
    /**
     * Sets the unique handle for the field style, which is used for registration and retrieval.
     *
     * @param string $handle
     *
     * @return self
     */
    public function handle(string $handle): self {
        $this->handle = $handle;

        $this->hook();
        return $this;
    }

    /**
     * Sets the blade view path that defines the template for rendering fields with this style.
     *
     * @param string $view
     *
     * @return self
     */
    public function view(string $view): self {
        $this->view = $view;

        $this->hook();
        return $this;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Retrieves the view path for the field style.
     *
     * @return string
     */
    public function getView(): string {
        return $this->view;
    }
}