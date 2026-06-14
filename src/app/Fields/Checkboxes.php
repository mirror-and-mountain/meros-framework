<?php 

namespace MM\Meros\App\Fields;

class Checkboxes extends Select {
    public static string $icon = 'tick';

    /**
     * Sets up the field's handle, supported features, etc.
     *
     * @return void
     */
    protected function initialise(): void {
        parent::initialise();
        $this->handle = 'checkboxes';
        $this->compatibleDataTypes = ['array.scalar'];

        $this->attribute('type', 'checkbox');
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
        return 'meros::forms.fields.choice';
    }

    /**
     * Retrieves the default value control for the field.
     *
     * @return string
     */
    public function getDefaultValueControl(): string {
        return 'meros::forms.fields.select-multi';
    }
}