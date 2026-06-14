<?php 

namespace MM\Meros\App\Fields;

class MultiSelect extends AdvancedSelect {
    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'multi_select';
        $this->compatibleDataTypes = ['array.scalar'];

        $this->attribute('multiple', true);
    }

    /**
     * Retrieves the field's value, ensuring it's returned as an array.
     *
     * @return mixed
     */
    public function getValue(): mixed {
        $value = parent::getValue();
        
        if (is_string($value)) {
            return array_map('trim', explode(',', $value));
        }

        return $value;
    }

    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.select-multi';
    }
}