<?php 

namespace MM\Meros\Services\Contracts\Admin;

use MM\Meros\Services\Contracts\FeatureDefinition;

abstract class MenuPageTemplate extends FeatureDefinition {
    /**
     * A unique handle for the template.
     *
     * @var string
     */
    public string $handle = '';

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
    protected string $pageTitle = '';

    /***************************
     * Feature Contract Methods
     ***************************/
    
    final protected function queue(): void {
        // MenuPageTemplate classes don't use the queue method
    }

    /**
     * Renders the content of the menu page. Must be implemented by concrete template classes.
     *
     * @return void
     */
    abstract public function render(): void;

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
     * Sets the title for the menu page using this template.
     * 
     * @param string $title The page title.
     *
     * @return void
     */
    public function setTitle(string $title): void {
        $this->pageTitle = $title;
    }
}