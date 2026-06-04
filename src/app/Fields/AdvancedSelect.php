<?php 

namespace MM\Meros\App\Fields;

class AdvancedSelect extends Select {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'advanced_select';

    /**
     * Choice features supported by the advanced select field.
     *
     * @var array
     */
    protected array $supports = [
        'allowAdd'
    ];

    /**
     * Determines if this advanced select field should use an advanced UI (e.g., tomselect).
     * Always set to true for advanced select fields.
     *
     * @return boolean
     */
    public function isAdvanced(): bool {
        return true;
    }
}