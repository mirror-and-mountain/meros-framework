<?php 

namespace MM\Meros\App\FieldWrappers;

use MM\Meros\Services\Contracts\Forms\FieldWrapper;

class SiteDefault extends FieldWrapper {
    /**
     * The unique handle for this field wrapper.
     *
     * @var string
     */
    public string $handle = 'site_default';

    /**
     * The blade view path for this field wrapper.
     *
     * @var string
     */
    protected string $view = 'meros::forms.field-wrappers.site-default';

    protected array $styleAttributes = [
        'input-size'                    => '--nf-input-size',
        'input-color'                   => '--nf-input-color',
        'input-font-size'               => '--nf-input-font-size',
        'input-radius'                  => '--nf-input-border-radius',
        'input-border-color'            => '--nf-input-border-color',
        'input-invalid-border-color'    => '--nf-invalid-input-border-color',
        'input-valid-border-color'      => '--nf-valid-input-border-color',
        'label-color'                   => '--nf-label-color',
        'label-font-size'               => '--nf-label-font-size',
    ];

    protected array $highlightedAttributes = [
        '--nf-input-focus-border-color',
     ];
}