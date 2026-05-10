<?php 

namespace MM\Meros\Services\Concerns;

use Illuminate\Support\Str;
use MM\Meros\App\Theme;

trait HasPreferences {
    /**
     * Default preferences.
     *
     * @var array
     */
    protected array $defaultPreferences = [
        'assets_path'                            => 'resources/assets/build', // No leading or trailing slashes
        'asset_groups_are_enabled_by_default'    => true, // Whether to enable discovered asset groups by default.
        'asset_groups_are_switchable_by_default' => true, // Whether to allow enabling/disabling asset groups in WP Admin by default.
        'blocks_path'                            => 'resources/blocks/build', // No leading or trailing slashes
        'blocks_are_enabled_by_default'          => true, // Whether to enable discovered blocks by default.
        'blocks_are_switchable_by_default'       => true, // Whether to allow enabling/disabling blocks in WP Admin by default.
        'components_path'                        => 'src/app/View/Components', // No leading or trailing slashes
        'livewire_path'                          => 'app/Livewire',
        'livewire_views_path'                    => 'resources/views/livewire', // No leading or trailing slashes
        'livewire_namespace'                     => 'App\\Livewire', // The namespace for Livewire components
        'views_path'                             => 'resources/views', // No leading or trailing slashes
        'routes_path'                            => 'routes', // No leading or trailing slashes
        'tables_path'                            => 'database/tables', // No leading or trailing slashes
    ];

    /**
     * Preferences that can be set by package/theme developers.
     *
     * @var array
     */
    protected array $preferences = [];

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
                'default_site_field_style'     => 'nice', // The default field style for the site.
                'default_admin_field_style'    => 'admin', // The default field style for the WP Admin.
                'allow_field_style_override'   => true, // Whether to allow overriding the default field styles on a per-fieldgroup or per-form basis.
            ];
        }
    }

    /**
     * Sets preference using the given key and value.
     * Values must match the type of the default preference value to be set.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    protected function setPreference(string $key, mixed $value): void {
        $exists      = array_key_exists($key, $this->defaultPreferences);
        $typeMatches = gettype($value) === gettype($this->defaultPreferences[$key]);

        if ($exists && $typeMatches) {
            if (Str::endsWith($key, '_path')) {
                $value = trim($value, '/'); // Remove leading and trailing slashes for path preferences.
            }
            
            $this->preferences[$key] = $value;
        }
    }

    /**
     * Returns the value of a specific preference.
     *
     * @param string $key
     * @param bool   $fullPath Whether to return the full path (including the default path) or just the custom value set by the developer (only relavant for path preferences).
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