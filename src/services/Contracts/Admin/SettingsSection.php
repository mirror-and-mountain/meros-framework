<?php 

namespace MM\Meros\Services\Contracts\Admin;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FeatureProvider;
use MM\Meros\Services\Contracts\FeatureDefinition;

class SettingsSection extends FeatureDefinition {
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
     * A description of the settings section.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The slug of the settings page that the section belongs to.
     *
     * @var string
     */
    protected string $page = '';

    /**
     * An array of additional arguments to pass to the settings section callback.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * The callback function that renders the content of the settings section.
     *
     * @var Closure|string|array|null
     */
    protected Closure|string|array|null $callback = null;

    /**
     * SettingsSection constructor.
     *
     * @param FeatureProvider $provider
     * @param array           $props
     */
    final public function __construct(
        FeatureProvider $provider,
        array           $props = []
    ) {
        $this->provider = $provider;
        
        if (empty($this->page)) {
            $this->page = $this->provider->getSettingsPageSlug();
        }

        $this->setProps($props);

        $this->queue();
    }

    /**
     * Queues the settings section to be loaded via a WordPress hook if all the required properties are set.
     * 
     * @return void
     */
    protected function queue(): void {
        $requiredProps = [
            'id',
            'title',
            'page',
         ];

        foreach ($requiredProps as $prop) {
            if (empty($this->$prop)) {
                 return;
            }
        }

        if ($this->callback === null) {
            $this->callback = function() {
                $this->render();
            };
        }

        if (!$this->queued) {
             add_action('admin_init', function() {
                $this->load();
            });
        }

        $this->queued = true;
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
            $this->page,
            $this->args
        );
    }

    /***************************
     * Rendering
     ***************************/

    /**
     * Default render method for the settings section. This will be used if no callback is provided.
     *
     * @return void
     */
    public function render(): void {
        if (empty($this->title) && empty($this->description)) {
            echo '';
        }

        if (!empty($this->title)) {
            echo '<h3 class="meros-settings-section-title">' . esc_html($this->title) . '</h3>';
        }

        if (!empty($this->description)) {
            echo '<p class="meros-settings-section-description">' . esc_html($this->description) . '</p>';
        }
    }

    /***************************
     * Public Chainable methods
     ***************************/
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

        $this->queue();
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

        $this->queue();
        return $this;
    }

    /**
     * Sets the description of the settings section.
     *
     * @param string $description The description of the settings section.
     *
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;

        $this->queue();
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
        $this->callback = $callback;

        $this->queue();
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

        $this->queue();
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
        return $this->page;
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