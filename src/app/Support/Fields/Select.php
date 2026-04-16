<?php 

namespace MM\Meros\App\Support\Fields;

class Select extends Field {
    public array $options = [];
    public bool  $multiple = false;
    public bool  $advanced = false;
    public array $advancedConfig = [
        'allow_add' => false
    ];

    public function configure(array $config): self {
        if (isset($config['multiple'])) {
            $this->multiple = $config['multiple'];
        }

        if (isset($config['options'])) {
            $this->options = $config['options'];
        }

        if (isset($config['advanced'])) {
            $this->advanced = $config['advanced'];
        }
    
        if (isset($config['allow_add'])) {
            $this->advancedConfig['allow_add'] = $config['allow_add'];
        }

        // Apply CSS classes based on configuration
        if ($this->advanced) {
            $this->class('advanced-select');
        } else {
            $this->classList = array_diff($this->classList, ['advanced-select']);
        }
        
        if ($this->advancedConfig['allow_add']) {
            $this->class('allow-add');
        } else {
            $this->classList = array_diff($this->classList, ['allow-add']);
        }

        // dd($this->options, $this->value);
        
        return $this;
    }

    public function options(array $options): self {
        return $this->configure(['options' => $options]);
    }

    public function multiple(bool $multiple = true): self {
        return $this->configure(['multiple' => $multiple]);
    }

    public function advanced(bool $advanced = true): self {
        return $this->configure(['advanced' => $advanced]);
    }

    public function allowAdd(bool $allowAdd = true): self {
        return $this->configure(['allow_add' => $allowAdd]);
    }

    /***************************
     * Rendering
     ***************************/
    public function getFieldComponent(): string {
        return 'meros::admin.fields.select';
    }
}