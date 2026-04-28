<?php 

namespace MM\Meros\Services\Contracts;

use Illuminate\Support\Str;

abstract class MenuPageTemplate {
    /**
     * The slug of the menu page using this instance of the template.
     *
     * @var string
     */
    protected string $pageSlug = '';

    /**
     * The title of the menu page using this instance of the template.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * Constructor
     *
     * @param array $props
     */
    public function __construct(array $props = []) {
        $this->setProps($props);
    }

    /**
     * Renders the content of the menu page. Must be implemented by concrete template classes.
     *
     * @return void
     */
    abstract public function render(): void;

    /**
     * Sets the properties of the menu page template based on the provided array of properties.
     *
     * @param array $props An associative array of properties to set on the template.
     * @return void
     */
    public function setProps(array $props = []): void {
        foreach ($props as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
                continue;
            }

            $camelKey = Str::camel($key);
            if (property_exists($this, $camelKey)) {
                $this->$camelKey = $value;
            }
        }
    }

    /**
     * Sets the slug for the menu page using this template.
     *
     * @param string $slug
     *
     * @return void
     */
    public function setSlug(string $slug): void {
        $this->pageSlug = $slug;
    }

    /**
     * Gets the slug of the menu page using this template.
     *
     * @return string The slug of the menu page.
     */
    public function setTitle(string $title): void {
        $this->title = $title;
    }
}