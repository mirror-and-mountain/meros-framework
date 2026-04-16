<?php 

namespace MM\Meros\App\Support\Fields\Concerns;

trait IsTextArea {
    public function configure(array $config): self {
        if (isset($config['rows'])) {
            $this->config['rows'] = $config['rows'];
        }

        return $this;
    }

    public function rows(int $rows): self {
        return $this->configure(['rows' => $rows]);
    }

    /***************************
     * Rendering
     ***************************/
    public function getFieldComponent(): string {
        return 'meros::fields.textarea';
    }
}