<?php 

namespace MM\Meros\App\FieldWrappers;

use MM\Meros\Services\Contracts\Forms\FieldWrapper;

class AdminSettings extends FieldWrapper {
    /**
     * The unique handle for this field wrapper.
     *
     * @var string
     */
    public string $handle = 'admin_settings';

    /**
     * The blade view path for this field wrapper.
     *
     * @var string
     */
    protected string $view = 'meros::forms.field-wrappers.admin-settings';
}