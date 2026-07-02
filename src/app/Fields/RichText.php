<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Forms\Field;

class RichText extends Field {
    public static string $icon = 'paint-brush';
    public static string $category = 'specialised';

    protected function initialise(): void {
        $this->handle = 'rich-text';
        $this->compatibleDataTypes = ['string'];
        $this->addSupports([
            'required',
            'disabled',
            'helpText'
        ]);
    }

    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.rich-text';
    }
}