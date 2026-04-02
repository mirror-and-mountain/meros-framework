<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Str;

use MM\Meros\App\Theme;
use MM\Meros\App\Package;

trait HasPreferences {
    /**
     * Default preferences.
     *
     * @var array
     */
    protected array $defaultPreferences = [
        'assets_path'                      => 'assets/build', // No leading or trailing slashes
        'assets_path_structure'            => '/**/{location}/*.{extension}', 
        'assets_are_enabled_by_default'    => true, // Whether to enable discovered assets by default.
        'assets_are_switchable_by_default' => true, // Whether to allow enabling/disabling assets in WP Admin by default.
        'blocks_path'                      => 'blocks/build', // No leading or trailing slashes
        'blocks_are_enabled_by_default'    => true, // Whether to enable discovered blocks by default.
        'blocks_are_switchable_by_default' => true, // Whether to allow enabling/disabling blocks in WP Admin by default.
        'components_path'                  => 'components', // No leading or trailing slashes
        'views_path'                       => 'views', // No leading or trailing slashes
        'routes_path'                      => 'routes', // No leading or trailing slashes
        'migrations_path'                  => 'database/migrations', // No leading or trailing slashes
        'settings_path'                    => 'config/settings.php', // No leading or trailing slashes
    ];

    /**
     * Preferences that can be set by package/theme developers.
     *
     * @var array
     */
    private array $preferences = [];

    /**
      * Initialises preferences based on the type of the item (theme or package).
      *
      * @return void
      */
    protected function initPreferences(): void {
        if ($this instanceof Theme) {
            $this->defaultPreferences = [
                ...$this->defaultPreferences,
                'allow_installers_in_wp_admin' => true, // Whether to allow installation of packages and themes via WP Admin.
            ];
        }

        else if ($this instanceof Package) {
            $this->defaultPreferences = [
                ...$this->defaultPreferences,
                'is_enabled_by_default' => true, // Whether the package is enabled by default.
                'is_switchable'         => true, // Whether the package can be switched on or off in WP Admin.
            ];
        }
    }

    /**
     * Sets preference using the given key and value.
     * Values must match the type of the default preference value to be set.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return void
     */
    protected function setPreference(string $key, mixed $value): void {
        $exists      = array_key_exists($key, $this->defaultPreferences);
        $typeMatches = gettype($value) === gettype($this->defaultPreferences[$key]);

        if ($exists && $typeMatches) {
            $this->preferences[$key] = $value;
        }
    }

    /**
     * Returns the value of a specific preference.
     *
     * @param  string $key
     * @param  bool   $fullPath Whether to return the full path (including the default path) or just the custom value set by the developer (only relavant for path preferences).
     *
     * @return mixed
     */
    final public function getPreference(string $key, bool $fullPath = true): mixed {
        if (Str::endsWith($key, '_path') && $fullPath) {
            if (isset($this->preferences[$key])) {
                return trailingslashit( $this->path ) . $this->preferences[$key];
            } else if (isset($this->defaultPreferences[$key])) {
                return trailingslashit( $this->path ) . $this->defaultPreferences[$key];
            } else {
                return null;
            }
        }
        return $this->preferences[$key] ?? $this->defaultPreferences[$key] ?? null;
    }
}