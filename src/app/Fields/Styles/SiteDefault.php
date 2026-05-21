<?php 

namespace MM\Meros\App\Fields\Styles;

use MM\Meros\Services\Contracts\Elements\FormStyle;

class SiteDefault extends FormStyle {
    /**
     * The unique handle for this form style.
     *
     * @var string
     */
    public string $handle = 'site_default';

    /**
     * The blade view path for this form style.
     *
     * @var string
     */
    protected string $view = 'meros::form-styles.site-default';

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