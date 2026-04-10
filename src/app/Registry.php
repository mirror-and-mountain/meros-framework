<?php 

namespace MM\Meros\App;

use MM\Meros\App\Contracts\Registry as Contract;
use MM\Meros\App\Contracts\FeatureDefinition;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class Registry implements Contract {
    public Collection $packages;
    public Collection $installables;
    public Collection $integrations;

    public Collection $settingsPages;
    public Collection $settingsSections;
    public Collection $settings;
    public Collection $settingsFields;

    public Collection $assets;
    public Collection $blocks;
    public Collection $components;

    public function __construct() {
        $this->packages         = collect([]);
        $this->installables     = collect([]);
        $this->integrations     = collect([]);

        $this->settingsPages    = collect([]);
        $this->settingsSections = collect([]);
        $this->settings         = collect([]);
        $this->settingsFields   = collect([]);

        $this->assets           = collect([]);
        $this->blocks           = collect([]);
        $this->components       = collect([]);
    }

    /**
     * Adds a package to the registry.
     *
     * @param  Package $package The package to add.
     * 
     * @return void
     */
    public function addPackage(Package $package): void {
        $this->packages->push($package);
    }

    /**
     * Generic method to add an item to one of this object's collections.
     * Specific methods will be added for individual collections e.g. addPackage(), addSettingPage() etc.
     *
     * @param  string $type The collection to add to e.g. 'settings', 'packages' etc.
     * @param  FeatureDefinition $item The item to add.
     * 
     * @return void
     */
    public function add(string $type, FeatureDefinition $item): void {
        if (property_exists($this, $type)) {
            if (!$this->{$type}->contains($item)) {
                $this->{$type}->push($item);
                return;
            }
        }

        $plural = Str::plural($type);
        if (property_exists($this, $plural)) {
            if (!$this->{$plural}->contains($item)) {
                $this->{$plural}->push($item);
                return;
            }
        }
    }

    /**
     * Generic method to get one of this object's collections.
     * Specific methods are also available below e.g. getSettings();
     *
     * @param  string $type The collection to get e.g. 'settings', 'packages' etc.
     * 
     * @return Collection
     */
    public function get(string $type): Collection {
        if (property_exists($this, $type)) {
            return $this->{$type};
        }

        return collect([]);
    }

    /**
     * Returns the packages collection.
     *
     * @return Collection
     */
    public function getPackages(): Collection {
        return $this->packages;
    }

    /**
     * Returns a single package using the given handle if present 
     * in the collection.
     *
     * @param  string $handle
     *
     * @return Package|null
     */
    public function getPackage(string $handle): Package|null {
        return $this->packages->firstWhere('handle', $handle);
    }

    /**
     * Returns the installables collection.
     *
     * @return Collection
     */
    public function getInstallables(): Collection {
        return $this->installables;
    }

    /**
     * Returns the settings pages collection.
     *
     * @return Collection
     */
    public function getSettingsPages(): Collection {
        return $this->settingsPages;
    }

    /**
     * Returns the settings sections collection.
     *
     * @return Collection
     */
    public function getSettingsSections(): Collection {
        return $this->settingsSections;
    }

    /**
     * Returns the settings collection.
     *
     * @return Collection
     */
    public function getSettings(): Collection {
        return $this->settings;
    }

    /**
     * Returns the assets collection.
     *
     * @return Collection
     */
    public function getAssets(): Collection {
        return $this->assets;
    }

    /**
     * Returns the blocks collection.
     *
     * @return Collection
     */
    public function getBlocks(): Collection {
        return $this->blocks;
    }

    /**
     * Returns the components collection.
     *
     * @return Collection
     */
    public function getComponents(): Collection {
        return $this->components;
    }

    /**
     * Returns whether the registry contains any blocks
     * 
     * @param bool $switchableOnly If true, only counts blocks that are switchable in WP Admin. Otherwise counts all registered blocks.
     *
     * @return boolean
     */
    public function hasBlocks(bool $switchableOnly = false): bool {
        return $switchableOnly 
            ? $this->blocks->filter->isSwitchable->isNotEmpty() 
            : $this->blocks->isNotEmpty();
    }

    /**
     * Returns whether the registry contains any assets
     * 
     * @param bool $switchableOnly If true, only counts assets that are switchable in WP Admin. Otherwise counts all registered assets.
     *
     * @return boolean
     */
    public function hasAssets(bool $switchableOnly = false): bool {
        return $switchableOnly 
            ? $this->assets->filter->isSwitchable->isNotEmpty() 
            : $this->assets->isNotEmpty();
    }

    /**
     * Returns whether the registry contains any settings pages
     *
     * @return boolean
     */
    public function hasSettingsPages(): bool {
        return $this->settingsPages->isNotEmpty();
    }

    /**
     * Returns whether the registry contains any settings sections
     *
     * @return boolean
     */
    public function hasSettingsSections(): bool {
        return $this->settingsSections->isNotEmpty();
    }

    /**
     * Returns whether the registry contains any settings
     *
     * @return boolean
     */
    public function hasSettings(): bool {
        return $this->settings->isNotEmpty();
    }

    /**
     * Returns whether the registry contains any packages
     *
     * @return boolean
     */
    public function hasPackages(): bool {
        return $this->packages->isNotEmpty();
    }

    /**
     * Returns whether the registry contains any installables
     *
     * @return boolean
     */
    public function hasInstallables(): bool {
        return $this->installables->isNotEmpty();
    }

    /**
     * Returns whether the registry contains any components
     *
     * @return boolean
     */
    public function hasComponents(): bool {
        return $this->components->isNotEmpty();
    }
}