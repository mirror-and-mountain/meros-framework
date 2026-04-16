<?php 

namespace MM\Meros\App\Support\Fields;

class Textarea extends Field {
    public function configure(array $config): self {
        if (isset($config['rows'])) {
            $this->config['rows'] = $config['rows'];
        }

        return $this;
    }

    /***************************
     * Rendering
     ***************************/
    public function getFieldComponent(): string {
        return 'meros::fields.textarea';
    }
}