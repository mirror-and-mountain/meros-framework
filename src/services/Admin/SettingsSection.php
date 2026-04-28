<?php 

namespace MM\Meros\Services\Admin;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FeatureDefinition;

final class SettingsSection extends FeatureDefinition {

    /**
     * The section's id.
     *
     * @var string
     */
    public string $id = '';

    /**
     * The sections title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * The slug of the settings page that the section belongs to.
     *
     * @var string
     */
    protected string $pageSlug = '';

    /**
     * An array of additional arguments to pass to the settings section callback.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * The callback function that renders the content of the settings section.
     *
     * @var Closure|null
     */
    protected ?Closure $callback = null;

    /**
     * The menu page that the settings section belongs to.
     *
     * @var MenuPage|null
     */
    protected ?MenuPage $page = null;

    /**
     * Sets the setting section as ready (or not) based on the state of the setting section's properties.
     * 
     * @return void
     */
    protected function hook(): void {
        $requiredProps = [
            'id',
            'title',
            'pageSlug',
            'callback'
         ];

         if (is_null($this->callback)) {
            $this->callback = function() {
                echo view('meros::admin.templates.provider-settings-section', [
                    'id'               => $this->id,
                    'title'            => $this->title,
                    'author'           => $this->provider->getAuthor(),
                    'authorUri'        => $this->provider->getAuthorUri(),
                    'authorSupportUri' => $this->provider->getAuthorSupportUri(),
                ]);
            };
         }

         foreach ($requiredProps as $prop) {
             if (!isset($this->$prop) || (is_string($this->$prop) && empty($this->$prop))) {
                 $this->ready = false;
                 return;
             }
         }


        $this->ready = true;

        if (!$this->loaded) {
             add_action('admin_init', function() {
                $this->load();
            });
        }

        $this->loaded = true;
    }

    /**
     * Adds the settings section to the specified settings page.
     *
     * @return void
     */
    protected function load(): void {
        add_settings_section(
            $this->id,
            $this->title,
            $this->callback,
            $this->pageSlug,
            $this->args
        );

        $this->loaded = true;
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Associates the settings section with a menu page. Can accept either an instance of MenuPage or a string representing the title of the menu page to associate with.
     *
     * @param MenuPage|string $page The menu page to associate the settings section with. Can be an instance of MenuPage or a string representing the title of the menu page.
     *
     * @return self
     */
    public function onPage(MenuPage|string $page): self {
        if ($page instanceof MenuPage) {
            $this->page     = $page;
            $this->pageSlug = $page->getSlug();
        } else {
            $this->pageSlug = Str::slug($page);
        }

        $this->hook();
        return $this;
    }

    /**
     * Sets the ID of the settings section.
     *
     * @param string $id The ID of the settings section.
     *
     * @return self
     */
    public function id(string $id): self {
        $this->id = Str::slug($id);

        if (empty($this->title)) {
            $this->title = Str::title(str_replace(['-', '_'], ' ', $id));
        }

        $this->hook();
        return $this;
    }

    /**
     * Sets the title of the settings section.
     *
     * @param string $title The title of the settings section.
     * 
     * @return self
     */
    public function title(string $title): self {
        $this->title = $title;

        if (empty($this->id)) {
            $this->id = Str::slug($title);
        }

        $this->hook();
        return $this;
    }

    /**
     * Sets the callback function that renders the content of the settings section.
     *
     * @param callable|Closure $callback The callback function that renders the content of the settings section.
     *
     * @return self
     */
    public function callback(callable|Closure $callback): self {
        $this->callback = $this->convertToClosure($callback);

        $this->hook();
        return $this;
    }

    /**
     * Sets additional arguments to pass to the settings section callback.
     *
     * @param array $args An associative array of additional arguments to pass to the settings section callback.
     *
     * @return self
     */
    public function args(array $args): self {
        $this->args = $args;

        $this->hook();
        return $this;
    }

    /***************************
     * Getters
     ***************************/
    /**
     * Gets the slug of the settings page that this section belongs to.
     *
     * @return string The slug of the settings page.
     */
    public function getPageSlug(): string {
        return $this->pageSlug;
    }

    /**
     * Gets the ID of the settings section.
     *
     * @return string The ID of the settings section.
     */
    public function getID(): string {
        return $this->id;
    }
}