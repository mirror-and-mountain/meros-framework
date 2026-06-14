<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Forms\Field;

abstract class Input extends Field {
    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        $this->handle = 'input';
        $this->addSupports([
            'required',
            'disabled',
            'placeholder',
            'helpText'
        ]);
    }

    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.input';
    }

    /**
     * Sets the type of input field to use (text, email, password, etc.).
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
     * @param boolean $show
     * @param string  $position
     *
     * @return self
     */
    public function showIcon(bool $show = true, string $position = 'left'): self {
        if ($this->supports('icon') && $show) {
            $this->class("icon-{$position}");
        } else {
            $this->removeClass(['icon-left', 'icon-right']);
        }

        return $this;
    }
}