<?php 

namespace MM\Meros\App\Support\Fields\Concerns;

trait HasFieldProps {
    public string $id = '';
    public string $name = '';
    public string $label = '';
    public string $description = '';
    public mixed  $value = null;
    public array  $classList = [];

    public bool  $required = false;
    public bool  $disabled = false;
    public array $config = [];

    /**
     * Configures the field with additional settings.
     *
     * @param array $config
     *
     * @return self
     */
    public function configure(array $config): self {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Makes the field required.
     *
     * @param boolean $required
     *
     * @return self
     */
    public function required(bool $required = true): self {
        $this->required = $required;
        return $this;
    }

    /**
     * Disables the field.
     *
     * @param boolean $disabled
     *
     * @return self
     */
    public function disabled(bool $disabled = true): self {
        $this->disabled = $disabled;
        return $this;
    }

    /**
     * Adds CSS classes to the field's class list.
     *
     * @param string|array $classes
     *
     * @return self
     */
    public function class(string|array $classes): self {
        $classes = is_array($classes) ? $classes : explode(',', $classes);
        $this->classList = array_merge($this->classList, $classes);
        return $this;
    }
}