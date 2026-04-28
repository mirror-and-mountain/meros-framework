<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Field;

class Textarea extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'textarea';

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
        return 'meros::fields.textarea';
    }
}