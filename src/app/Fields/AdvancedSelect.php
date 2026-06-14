<?php 

namespace MM\Meros\App\Fields;

class AdvancedSelect extends Select {
    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        $this->handle = 'advanced_select';
        $this->compatibleDataTypes = ['string'];

        $this->attribute('data-allow-add', 'false');
        $this->attribute('data-advanced', 'true');
    }

    /**
     * Sets whether the field allows adding new options on the fly.
     *
     * @param boolean $allow
     *
     * @return self
     */
    public function allowAdd(bool $allow = true): self {
        $this->attributes['data-allow-add'] = $allow ? 'true' : 'false';
        return $this;
    }

    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.select-advanced';
    }
}