<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Forms\Field;

abstract class Input extends Field {
    protected ?bool $showsIcon = null;
    protected ?string $iconPosition = null;

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
            'helpText',
            'dynamicDefault'
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
            $this->showsIcon = true;
            $this->iconPosition = $position;
        } else {
            $this->removeClass(['icon-left', 'icon-right']);
            $this->showsIcon = false;
            $this->iconPosition = null;
        }

        return $this;
    }

    /**
     * Sets the position of the icon in the field input, if supported.
     *
     * @param string $position
     *
     * @return self
     */
    public function iconPosition(string $position): self {
        if ($this->supports('icon') && $this->showsIcon) {
            $this->removeClass(['icon-left', 'icon-right']);
            $this->class("icon-{$position}");
            $this->iconPosition = $position;
        }

        return $this;
    }

    /**
     * Converts the field's properties to an array format suitable for JSON serialization
     * 
     * @param boolean $asString Whether to return the JSON as a string or an array.
     * @param string  ...$flags Optional flags to pass to json_encode if $asString is true.
     *
     * @return array|string
     */
    public function toJson(bool $asString = false, string ...$flags): array|string {
        $json = parent::toJson(false);

        $json['properties']['showsIcon'] = $this->supports('icon') ?  ($this->showsIcon ?? true) : null;
        $json['properties']['iconPosition'] = $this->supports('icon') ? ($this->iconPosition ?? 'left') : null;

        if ($asString) {
            return json_encode($json, ...$flags);
        }

        return $json;
    }
}