<?php 

namespace MM\Meros\App\Features\Settings;

use Closure;

use MM\Meros\App\Features\Feature;
use MM\Meros\App\Contracts\SettingsRegistrar;

use MM\Meros\App\Facades\Registry;

class SettingsPage extends Feature {
    /**
     * The area of WP Admin where the settings page should be displayed.
     *
     * @var string
     */
    public string $area;

    /**
     * The function to be called for the given area.
     *
     * @var string
     */
    public string $areaFunc;

    /**
     * The human-readable title of the settings page.
     *
     * @var string
     */
    public string $title;

    /**
     * The human-readable title of the menu item for the settings page.
     *
     * @var string
     */
    public string $menuTitle;

    /**
     * The capability required to access the settings page.
     *
     * @var string
     */
    public string $capability;

    /**
     * The position of the menu item for the settings page.
     *
     * @var int|null
     */
    public int|null $position;

    /**
     * The icon for the settings page menu item.
     *
     * @var string|null
     */
    public string|null $icon;

    /**
     * Whether the settings page should use AJAX for saving.
     *
     * @var bool
     */
    public bool $ajax;

    /**
     * The callback used to render the settings page.
     *
     * @var Closure
     */
    public Closure $callback;

    public function __construct(
        public SettingsRegistrar $source
    ) {
        $this->setSchema();
    }

    /**
     * Creates a SettingsPage instance from a config array and registers it.
     *
     * @param  array $config Configuration array for the settings page.
     * 
     * @return self  An instance of the SettingsPage feature.
     */
    public function make(array $config): self {
        $sanitizedConfig = $this->sanitizeConfig($config);
        if ($sanitizedConfig !== false) {
            $this->title      = $sanitizedConfig['page_title'];
            $this->menuTitle  = $sanitizedConfig['menu_title'];
            $this->capability = $sanitizedConfig['capability'];
            $this->handle     = $sanitizedConfig['menu_slug'];

            $this->callback   = $this->convertToClosure($sanitizedConfig['callback']);

            $this->position   = $sanitizedConfig['position'];
            $this->icon       = $sanitizedConfig['icon_url'];

            $this->area       = $sanitizedConfig['area'];
            $this->ajax       = $sanitizedConfig['ajax'];

            $this->ready = true;

            // Determine the appropriate function to call based on the specified area
            $this->areaFunc = $this->getAreaFunc($this->area);

            // Hook the load method to the admin_menu action to register the settings page
            add_action('admin_menu', [$this, 'load']);
        }

        Registry::add('settingsPages', $this);

        return $this;
    }

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

    /**
     * Set the configuration schema for the settings page.
     *
     * @return void
     */
    protected function setSchema(): void {
        $this->configSchema = [
            'page_title' => ['type' => 'string', 'required' => true],
            'menu_title' => ['type' => 'string', 'required' => true],
            'menu_slug'  => ['type' => 'string', 'required' => true],
            'callback'   => ['type' => 'callable', 'required' => true],
            'area'       => ['type' => 'string', 'required' => false, 'default' => 'options'],
            'capability' => ['type' => 'string', 'required' => false, 'default' => 'manage_options'],
            'position'   => ['type' => 'integer|null', 'required' => false, 'default' => null],
            'icon_url'   => ['type' => 'string|null', 'required' => false, 'default' => null],
            'ajax'       => ['type' => 'boolean', 'required' => false, 'default' => false]
        ];
    }

    /**
     * Loads the settings page by hooking it into WordPress.
     *
     * @return void
     */
    final public function load(): void {
        if (! $this->ready) {
            return;
        }

        if ($this->areaFunc !== 'add_menu_page') {
            call_user_func(
                $this->areaFunc, 
                $this->title, 
                $this->menuTitle, 
                $this->capability, 
                $this->handle, 
                $this->callback,
                $this->position
            );
        } else {
            call_user_func(
                $this->areaFunc, 
                $this->title, 
                $this->menuTitle, 
                $this->capability, 
                $this->handle, 
                $this->callback, 
                $this->icon, 
                $this->position
            );
        }
    }
}