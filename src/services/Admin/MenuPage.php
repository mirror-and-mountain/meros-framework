<?php 

namespace MM\Meros\Services\Admin;

use Closure;
use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FeatureDefinition;
use MM\Meros\Services\Contracts\MenuPageTemplate;

use MM\Meros\Services\Admin\Templates\TabbedSettingsPage;
use MM\Meros\Services\Admin\Templates\SimpleSettingsPage;

use MM\Meros\Facades\SettingsSections;

use MM\Meros\Support\ClassInfo;

class MenuPage extends FeatureDefinition {
    /**
     * The slug of the menu page.
     *
     * @var string
     */
    public string $slug = '';

    /**
     * The area of WP Admin where the menu page should be displayed.
     *
     * @var string
     */
    protected string $area = 'menu';

    /**
     * The function to call to add the menu page to WP Admin. Determined by the specified area.
     *
     * @var string
     */
    protected string $areaFunc = 'add_menu_page';

    /**
     * The page title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * The menu title.
     *
     * @var string
     */
    protected string $menuTitle = '';

    /**
     * The capability required for users to access the menu page.
     *
     * @var string
     */
    protected string $capability = 'manage_options';

    /**
     * The icon for the menu page.
     *
     * @var string|null
     */
    protected ?string $icon = 'dashicons-admin-generic';

    /**
     * The position of the menu page in the admin menu.
     *
     * @var integer|null
     */
    protected ?int $position = null;

    /**
     * The callback function that renders the content of the menu page.
     *
     * @var Closure|null
     */
    protected ?Closure $callback = null;

    /**
     * The template for the menu page. If set, the template's render method will be called to render the content of the menu page instead of the callback function.
     *
     * @var MenuPageTemplate|null
     */
    protected ?MenuPageTemplate $template  = null;

    /**
     * Sets the menu page as ready (or not) based on the menu page's current configuration.
     *
     * @return void
     */
    protected function hook(): void {
        $requiredProps = [
            'areaFunc',
            'title',
            'menuTitle',
            'capability',
            'slug'
        ];

        if (is_null($this->callback) && is_null($this->template)) {
            $this->ready = false;
            return;
        }

        foreach ($requiredProps as $prop) {
            if (!isset($this->$prop) || (is_string($this->$prop) && empty($this->$prop))) {
                $this->ready = false;
                return;
            }
        }

        if ($this->template !== null) {
            $this->template->setSlug($this->slug);
            $this->template->setTitle($this->title);
            $this->callback = function() {
                $this->render();
            };
        }

        $this->ready = true;

        if (!$this->loaded) {
             add_action('admin_menu', function() {
                $this->load();
            });
        }

        $this->loaded = true;
    }

    /**
     * Loads the settings page by hooking it into WordPress.
     *
     * @return void
     */
    protected function load(): void {
        if ($this->areaFunc !== 'add_menu_page') {
            call_user_func(
                $this->areaFunc, 
                $this->title, 
                $this->menuTitle, 
                $this->capability, 
                $this->slug, 
                $this->callback,
                $this->position
            );
        } else {
            call_user_func(
                $this->areaFunc, 
                $this->title, 
                $this->menuTitle, 
                $this->capability, 
                $this->slug, 
                $this->callback, 
                $this->icon, 
                $this->position
            );
        }
    }

    /**
     * Renders the menu page's template if it exists.
     *
     * @return void
     */
    protected function render(): void {
        if ($this->template !== null) {
            $this->template->render();
        }
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the title of the menu page. If the menu title is not already set, it will be set to the same value as the title. 
     * If the slug is not already set, it will be generated from the title.
     *
     * @param string $title
     *
     * @return self
     */
    public function title(string $title): self {
        $this->title = $title;

        if ($this->template !== null) {
            $this->template->setTitle($title);
        }

        if (empty($this->menuTitle)) {
            $this->menuTitle = $title;
        }

        if (empty($this->slug)) {
            $this->slug = Str::slug($title);
        }

        $this->hook();
        return $this;
    }

    /**
     * Sets the menu title of the menu page. If the title is not already set, it will be set to the same value as the menu title. 
     * If the slug is not already set, it will be generated from the menu title.
     *
     * @param string $menuTitle
     *
     * @return self
     */
    public function menuTitle(string $menuTitle): self {
        $this->menuTitle = $menuTitle;

        if (empty($this->title)) {
            $this->title = $menuTitle;
        }

        if (empty($this->slug)) {
            $this->slug = Str::slug($menuTitle);
        }

        $this->hook();
        return $this;
    }

    /**
     * Sets the area of WP Admin where the menu page should be displayed.
     *
     * @param string $area The area of WP Admin where the menu page should be displayed. Can be 'menu', 'options', 'tools', 'theme', or 'users'.
     * @return self
     */
    public function in(string $area): self {
        $this->area     = $area;
        $this->areaFunc = $this->getAreaFunc($area);

        $this->hook();
        return $this;
    }

    /**
     * Sets the capability required for users to access the menu page.
     *
     * @param string $capability The capability required for users to access the menu page.
     * @return self
     */
    public function capability(string $capability): self {
        $this->capability = $capability;

        $this->hook();
        return $this;
    }

    /**
     * Sets the slug of the menu page.
     *
     * @param string $slug The slug of the menu page.
     * @return self
     */
    public function slug(string $slug): self {
        $this->slug = Str::slug($slug);

        if ($this->template !== null) {
            $this->template->setSlug($this->slug);
        }

        if (empty($this->title)) {
            $this->title = Str::title(str_replace(['-', '_'], ' ', $slug));
        }

        if (empty($this->menuTitle)) {
            $this->menuTitle = Str::title(str_replace(['-', '_'], ' ', $slug));
        }

        $this->hook();
        return $this;
    }

    /**
     * Sets the icon for the menu page.
     *
     * @param string $icon
     *
     * @return self
     */
    public function icon(string $icon): self {
        $this->icon = $icon;

        $this->hook();
        return $this;
    }

    /**
     * Sets the position of the menu page in the admin menu.
     *
     * @param int $position The position of the menu page in the admin menu.
     * 
     * @return self
     */
    public function position(int $position): self {
        $this->position = $position;

        $this->hook();
        return $this;
    }

    /**
     * Sets the callback function that renders the content of the menu page.
     *
     * @param callable|Closure $callback
     *
     * @return self
     */
    public function callback(callable|Closure $callback): self {
        $this->callback = $this->convertToClosure($callback);

        $this->hook();
        return $this;
    }

    /**
     * Sets the template for the menu page.
     *
     * @param string|MenuPageTemplate $template The template to use for the menu page. Can be a string representing a predefined template or an instance of MenuPageTemplate.
     * @param array $props An associative array of properties to set on the template instance if a MenuPageTemplate object is provided.
     *
     * @return self
     */
    public function template(string|MenuPageTemplate $template, array $props = []): self {
        if (is_string($template)) {
            $map = [
                'simple-settings' => SimpleSettingsPage::class,
                'tabbed-settings' => TabbedSettingsPage::class
            ];

            if (in_array($template, array_keys($map))) {
                $class = $map[$template];
                $this->template = new $class($props);
            }

            else {
                $class = ClassInfo::get($template);
                if ($class->extends(MenuPageTemplate::class)) {
                    $this->template = new $template($props);
                }
            }
        }

        else if ($template instanceof MenuPageTemplate) {
            $this->template = $template;
            $this->template->setProps($props);
        }

        $this->hook();
        return $this;
    }

    /**
     * Associates a settings section with the menu page. Can accept either an instance of SettingsSection or a string representing the ID of the settings section to create and associate with the menu page.
     *
     * @param string|SettingsSection $section
     *
     * @return SettingsSection
     */
    public function withSettingsSection(string|SettingsSection $section): SettingsSection {
        if ($section instanceof SettingsSection) {
            $section->onPage($this);
        }

        else {
            $section = SettingsSections::make(['id' => $section])->onPage($this);
        }

        return $section;
    }

    /***************************
     * Getters
     ***************************/
    /**
     * Gets the slug of the menu page.
     *
     * @return string The slug of the menu page.
     */
    public function getSlug(): string {
        return $this->slug;
    }

    /**
     * Gets the title of the menu page.
     *
     * @return string The title of the menu page.
     */
    public function getTitle(): string {
        return $this->title;
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