<?php

namespace MM\Meros\Contracts\Features\Components\Concerns;

use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsSerializable;
use MM\Meros\Contracts\Features\Concerns\SanitizesHtml;

trait IsFormComponent {
    /**
     * The component's HTML attributes.
     *
     * @var array
     */
    protected array $attributes = [];

    /**
     * The component's CSS classes.
     *
     * @var array
     */
    protected array $classes = [];

    use IsRegistrable, IsSerializable, SanitizesHtml;

    /**
     * Adds a CSS class to the component's classes array.
     *
     * @param string $class
     *
     * @return static
     */
    public function class(string $class): static {
        $this->classes[] = $class;
        return $this;
    }

    /**
     * Returns the component's CSS classes as a string.
     *
     * @return string
     */
    public function getClassString(): string {
        return $this->classesToString();
    }

    /**
     * Removes a CSS class from the component's classes array.
     *
     * @param string $class
     *
     * @return static
     */
    public function removeClass(string $class): static {
        $this->classes = array_filter($this->classes, fn($c) => $c !== $class);
        return $this;
    }


    /**
     * Adds an attribute to the component's html attributes array.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return static
     */
    public function attribute(string $key, mixed $value): static {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Checks if the component has a specific attribute in its html attributes array.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function hasAttribute(string $key): bool {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Returns the component's HTML attributes as a string.
     *
     * @return string
     */
    public function getAttributeString(): string {
        return $this->attributesToString();
    }

    /**
     * Removes an attribute from the component's html attributes array.
     *
     * @param string $key
     *
     * @return static
     */
    public function removeAttribute(string $key): static {
        unset($this->attributes[$key]);
        return $this;
    }

    /**
     * Returns the component's HTML attributes as a string.
     *
     * @return string
     */
    protected function attributesToString(): string {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $attributes[] = $key;
                }
            } else {
                $attributes[] = sprintf('%s="%s"', $key, htmlspecialchars((string)$value, ENT_QUOTES));
            }
        }

        return implode(' ', $attributes);
    }

    /**
     * Returns the component's CSS classes as a string.
     *
     * @return string
     */
    protected function classesToString(): string {
        return implode(' ', $this->classes);
    }
}

