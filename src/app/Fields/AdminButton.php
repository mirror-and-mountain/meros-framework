<?php 

namespace MM\Meros\App\Fields;

use MM\Meros\Services\Contracts\Elements\Field;

class AdminButton extends Field {
    /**
     * The unique identifier for the field, used for resolution.
     *
     * @var string
     */
    public string $handle = 'admin_button';

    protected array $classList = ['button', 'button-primary'];

    protected array $attributes = [
        'href'   => '#',
        'target' => '_self',
    ];


    /***************************
     * Fluent Setters
     ***************************/

    public function link(string $href, string $target = '_self'): self {
        $this->attribute('href', $href);
        $this->attribute('target', $target);
        return $this;
    }

    public function openInNewTab(): self {
        $this->attribute('target', '_blank');
        return $this;
    }

    public function primary(): self {
        $this->classList = array_diff($this->classList, ['button-secondary']);
        $this->class('button-primary');
        return $this;
    }

    public function secondary(): self {
        $this->classList = array_diff($this->classList, ['button-primary']);
        $this->class('button-secondary');
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
        return 'meros::fields.admin-button';
    }
}