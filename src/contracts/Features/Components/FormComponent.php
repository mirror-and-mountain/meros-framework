<?php

namespace MM\Meros\Contracts\Features\Components;

use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Serializable;

interface FormComponent extends Registrable, Serializable {
    /**
     * Renders the form component.
     *
     * @param array $properties      Additional properties to pass to the view.
     * @param bool  $mergeProperties Whether to merge the additional properties with the component's properties.
     *
     * @return void
     */
    public function render(array $properties = [], bool $mergeProperties = false): void;

    /**
     * Returns the form component's HTML as a string. This method can be used to get the component's
     * HTML without directly echoing it.
     *
     * @param array $properties      Additional properties to pass to the view.
     * @param bool  $mergeProperties Whether to merge the additional properties with the component's properties.
     *
     * @return string
     */
    public function html(array $properties = [], bool $mergeProperties = false): string;
}