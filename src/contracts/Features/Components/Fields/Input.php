<?php

namespace MM\Meros\Contracts\Features\Components\Fields;

use MM\Meros\Contracts\Features\Components\Field;

abstract class Input extends Field {
    protected bool   $showsIcon = false;
    protected string $iconPosition = 'left';
    
    final protected function init(): void {
        parent::init();
        
        $this->view('meros::forms.fields.input');
        $this->setSerializableProperties(['showsIcon', 'iconPosition']);
    }

    /**
     * Sets the 'type' attribute of the input field.
     * 
     * Should be called in concrete implementations of this class to set the input type
     * in their configure() method.
     *
     * @param string $type
     *
     * @return void
     */
    protected function inputType(string $type): void {
        $this->attribute('type', $type);
    }

    /**
     * Sets the field to show an icon in the field input, if supported.
     *
     * @param bool|string $show
     * @param string      $position
     *
     * @return static
     */
    public function showIcon(string|bool $show = 'left', string $position = 'left'): static {
        if (is_string($show)) {
            $position = $show;
            $show = true;
        }

        if ($this->supports('icon') && $show) {
            $this->class("icon-{$position}");
            $this->showsIcon = true;
            $this->iconPosition = $position;
        }

        if ($this->supports('icon') && !$show) {
            $this->removeClass("icon-{$this->iconPosition}");
            $this->showsIcon = false;
        }

        return $this;
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    /**
     * Ensures that the 'type' attribute is set to 'text' if it hasn't been set already.
     *
     * @param array $properties
     *
     * @return array
     */
    protected function filterSerializedProperties(array $properties): array {
        if (!collect($this->attributes)->has('type')) {
            $this->inputType('text');
        }

        return $properties;
    }
}