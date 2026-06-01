<?php 

namespace MM\Meros\Services\Contracts\Forms;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

abstract class FieldWrapper extends FeatureDefinition {
    /**
     * A unique handle for the field wrapper, used for registration and retrieval.
     *
     * @var string
     */
    public string $handle = '';

    /**
     * A fully-qualified blade view path that defines the template for rendering forms with this field wrapper.
     *
     * @var string
     */
    protected string $view = '';

    /**
     * An array of style attributes that can be applied to forms using this field wrapper.
     *
     * @var array
     */
    protected array $styleAttributes = [];

    /**
     * An array of style attributes that should be influenced when the form's highlight colour changes.
     *
     * @var array
     */
    protected array $highlightedAttributes = [];

    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        parent::__construct($provider, $props);
        $this->parseStyleAttributes();
    }

    private function parseStyleAttributes(): void {
        if (empty($this->styleAttributes)) {
            return;
        }

        $userAttributes = $this->styleAttributes;

        $this->styleAttributes = array_filter([
            'input-size'                    => $userAttributes['input-size'] ?? null,
            'input-color'                   => $userAttributes['input-color'] ?? null,
            'input-font-size'               => $userAttributes['input-font-size'] ?? null,
            'input-font-family'             => $userAttributes['input-font-family'] ?? null,
            'input-radius'                  => $userAttributes['input-radius'] ?? null,
            'input-border-color'            => $userAttributes['input-border-color'] ?? null,
            'input-invalid-border-color'    => $userAttributes['input-invalid-border-color'] ?? null,
            'input-valid-border-color'      => $userAttributes['input-valid-border-color'] ?? null,

            'label-color'                   => $userAttributes['label-color'] ?? null,
            'label-font-size'               => $userAttributes['label-font-size'] ?? null,
            'label-font-weight'             => $userAttributes['label-font-weight'] ?? null,
            'label-font-family'             => $userAttributes['label-font-family'] ?? null,
            'label-spacing'                 => $userAttributes['label-spacing'] ?? null,

            'help-text-color'               => $userAttributes['help-text-color'] ?? null,
            'help-text-font-size'           => $userAttributes['help-text-font-size'] ?? null,
            'help-text-font-weight'         => $userAttributes['help-text-font-weight'] ?? null,
            'help-text-font-family'         => $userAttributes['help-text-font-family'] ?? null,
            'help-text-spacing'             => $userAttributes['help-text-spacing'] ?? null,

            'button-color'                  => $userAttributes['button-color'] ?? null,
            'button-font-size'              => $userAttributes['button-font-size'] ?? null,
            'button-font-weight'            => $userAttributes['button-font-weight'] ?? null,
            'button-font-family'            => $userAttributes['button-font-family'] ?? null,
            'button-background-color'       => $userAttributes['button-background-color'] ?? null,
            'button-border-color'           => $userAttributes['button-border-color'] ?? null,
            'button-border-radius'          => $userAttributes['button-border-radius'] ?? null,

            'field-spacing'                 => $userAttributes['field-spacing'] ?? null,
            'row-spacing'                   => $userAttributes['row-spacing'] ?? null,
        ], fn($value) => $value !== null);
    }

    /***************************
     * Contract Methods
     ***************************/

    final protected function queue(): void {
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