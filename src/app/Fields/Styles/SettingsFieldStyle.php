<?php 

namespace MM\Meros\App\Fields\Styles;

use MM\Meros\Services\Contracts\Elements\FieldStyle;

class SettingsFieldStyle extends FieldStyle {
    /**
     * The unique handle for this field style.
     *
     * @var string
     */
    public string $handle = 'settings';

    /**
     * The blade view path for this field style.
     *
     * @var string
     */
    protected string $view = 'meros::fields.styles.settings';
}