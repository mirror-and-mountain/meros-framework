<?php 

namespace MM\Meros\App\Support\Settings;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Support\Feature;

class AdminPage extends Feature {

    public string  $area;
    public string  $areaFunc;
    public string  $title;
    public string  $menuTitle;
    public string  $capability;
    public string  $slug;
    public ?string $icon = null;
    public ?int    $position = null;

    public Closure $callback;

    public function __construct(
        public FeatureProvider $source,
        string $slug
    ) {
        $this->slug       = Str::slug($slug);
        $this->title      = Str::title(Str::replace(['-', '_'], ' ', $slug));
        $this->menuTitle  = $this->title;
        $this->capability = 'manage_options';
        $this->area       = 'admin_menu';
        $this->areaFunc   = $this->getAreaFunc($this->area);

        add_action('admin_menu', function() {
            $this->load($this);
        });

        $this->setReady();
        $this->addToRegistry();
    }

    /**
     * Sets the setting page as ready (or not) based on the setting page's current configuration.
     *
     * @return void
     */
    protected function setReady(): void {
        if (!isset(
            $this->areaFunc,
            $this->title,
            $this->menuTitle,
            $this->capability,
            $this->slug,
            $this->callback
        )) {
            $this->ready = false;
            return;
        }

        $this->ready = true;
    }

    /**
     * Loads the settings page by hooking it into WordPress.
     *
     * @return void
     */
    protected function load(Feature $instance): void {
        if (!$instance->ready) {
            return;
        }

        if ($instance->areaFunc !== 'add_menu_page') {
            call_user_func(
                $instance->areaFunc, 
                $instance->title, 
                $instance->menuTitle, 
                $instance->capability, 
                $instance->slug, 
                $instance->callback,
                $instance->position
            );
        } else {
            call_user_func(
                $instance->areaFunc, 
                $instance->title, 
                $instance->menuTitle, 
                $instance->capability, 
                $instance->slug, 
                $instance->callback, 
                $instance->icon, 
                $instance->position
            );
        }
    }

    /***************************
     * Public Chainable methods
     ***************************/

    public function in(string $area): self {
        $this->area     = $area;
        $this->areaFunc = $this->getAreaFunc($area);

        $this->setReady();
        return $this;
    }

    public function capability(string $capability): self {
        $this->capability = $capability;

        $this->setReady();
        return $this;
    }

    public function slug(string $slug): self {
        $this->slug = Str::slug($slug);

        $this->setReady();
        return $this;
    }

    public function icon(string $icon): self {
        $this->icon = $icon;

        $this->setReady();
        return $this;
    }

    public function position(int $position): self {
        $this->position = $position;

        $this->setReady();
        return $this;
    }

    public function withCallback(callable|Closure $callback): self {
        $this->callback = $this->convertToClosure($callback);

        $this->setReady();
        return $this;
    }

    public function withSettingsSection(string|SettingsSection $section): SettingsSection {
        if ($section instanceof SettingsSection) {
            $section->onPage($this);
        }

        else {
            $section = app(SettingsSection::class, [
                'source' => $this->source,
                'id'     => $section
            ])->onPage($this);
        }

        return $section;
    }

    /***************************
     * Helpers
     ***************************/

    /**
     * Determines the appropriate function to call based on the specified area.
     *
     * @param string $area The area of WP Admin where the settings page should be displayed.
     * 
     * @return string The function to be called for the given area.
     */
    private function getAreaFunc(string $area): string {
        return match ($area) {
            'options' => 'add_options_page',
            'tools'   => 'add_management_page',
            'theme'   => 'add_theme_page',
            'users'   => 'add_users_page',
            default   => 'add_menu_page',
        };
    }
}