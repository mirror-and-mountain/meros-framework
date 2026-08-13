<?php 

namespace MM\Meros\Contracts\Admin;

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

    /**
     * An optional introduction or description for the menu page.
     *
     * @var string
     */
    protected string $pageIntro = '';

    /**
     * The fully-qualified view path for the template's view file.
     *
     * @var string
     */
    protected string $view = '';

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

    /**
     * Sets the introduction or description for the menu page using this template.
     *
     * @param string $intro The page introduction or description.
     *
     * @return void
     */
    public function setIntro(string $intro): void {
        $this->pageIntro = $intro;
    }
}