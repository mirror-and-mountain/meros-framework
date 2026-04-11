<?php 

namespace MM\Meros\App\Settings;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Support\Feature;

final class SettingsSection extends Feature {

    public string $id;
    public string $title;
    public string $pageSlug;
    public array  $args = [];

    public Closure $callback;

    // The admin page instance that this section belongs to.
    protected ?AdminPage $page = null;

    public function __construct(
        public FeatureProvider $source,
        string $id
    ) {
        $this->id    = Str::slug($id);
        $this->title = Str::title(Str::replace(['-', '_'], ' ', $id));

        add_action('admin_init', function() {
            $this->load($this);
        });

        $this->addToRegistry();
    }

    /**
     * Sets the setting section as ready (or not) based on the state of the setting section's properties.
     * 
     * @return void
     */
    protected function setReady(): void {
        if (!isset(
            $this->id,
            $this->title,
            $this->pageSlug,
            $this->callback
        )) {
            $this->ready = false;
            return;
        }

        $this->ready = true;
    }

    /**
     * Adds the settings section to the specified settings page.
     *
     * @return void
     */
    protected function load(Feature $instance): void {
        if (!$instance->ready) {
            return;
        }

        add_settings_section(
            $instance->id,
            $instance->title,
            $instance->callback,
            $instance->pageSlug,
            $instance->args
        );

        $this->loaded = true;
    }
    /***************************
     * Public Chainable methods
     ***************************/

    public function onPage(AdminPage|string $page): self {
        if ($page instanceof AdminPage) {
            $this->page     = $page;
            $this->pageSlug = $page->slug;
        } else {
            $this->pageSlug = Str::slug($page);
        }

        $this->setReady();
        return $this;
    }

    public function title(string $title): self {
        $this->title = $title;

        $this->setReady();
        return $this;
    }

    public function withCallback(callable|Closure $callback): self {
        $this->callback = $this->convertToClosure($callback);

        $this->setReady();
        return $this;
    }

    public function args(array $args): self {
        $this->args = $args;

        $this->setReady();
        return $this;
    }
}