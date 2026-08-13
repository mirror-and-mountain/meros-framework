<?php 

namespace MM\Meros\App\Fields\Wrappers;

use MM\Meros\Services\Contracts\Forms\FieldWrapper;

class AdminDefault extends FieldWrapper {
    /**
     * The unique handle for this field wrapper.
     *
     * @var string
     */
    public string $handle = 'admin_default';

    /**
     * The blade view path for this field wrapper.
     *
     * @var string
     */
    protected string $view = 'meros::forms.field-wrappers.admin-default';
}