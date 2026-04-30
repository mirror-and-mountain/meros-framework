<?php 

namespace MM\Meros\Services\Contracts\Elements;

use MM\Meros\Services\Contracts\FeatureDefinition;

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

    /***************************
     * Feature Contract Methods
     ***************************/

    protected function queue(): void {
        // Field styles don't use the queue method.
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