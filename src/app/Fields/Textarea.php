<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Forms\Field;

class Textarea extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'textarea';

    /**
     * The icon for the field, used in the form builder UI.
     *
     * @var string
     */
    public static string $icon = 'bars-long';

    /**
     * Default attributes for the textarea field.
     *
     * @var array
     */
    protected array $attributes = [
        'rows' => 3,
    ];

    /**
     * An array of data types that this field is compatible with.
     *
     * @var array
     */
    protected array $compatibleDataTypes = [
        'string'
    ];

    /**
     * Whether to force the field to take up the full width of its container, regardless of the width setting.
     *
     * @var bool
     */
    protected bool $forceFullWidth = true;


    /***************************
     * Fluent Setters
     ***************************/
    /**
     * Sets the number of rows for the textarea input.
     *
     * @param integer $rows
     *
     * @return self
     */
    public function rows(int $rows): self {
        $this->attribute('rows', $rows);
        return $this;
    }
    

    /***************************
     * Rendering
     ***************************/
    /**
     * Retrieves the blade component to use in the render() method.
     *
     * @return string
     */
    public function getFieldComponent(): string {
        return 'meros::forms.fields.textarea';
    }
}