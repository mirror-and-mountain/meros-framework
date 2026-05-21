<?php 

namespace MM\Meros\App\Fields\Styles;

use MM\Meros\Services\Contracts\Elements\FormStyle;

class AdminDefault extends FormStyle {
    /**
     * The unique handle for this field style.
     *
     * @var string
     */
    public string $handle = 'admin_default';

    /**
     * The blade view path for this field style.
     *
     * @var string
     */
    protected string $view = 'meros::form-styles.admin-default';
}