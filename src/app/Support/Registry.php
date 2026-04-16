<?php 

namespace MM\Meros\App\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

use MM\Meros\App\Contracts\FeatureBuilder;

class Registry {
    public Collection $installables;
    public Collection $integrations;

    public Collection $adminPages;
    public Collection $settingsSections;
    public Collection $settings;
    public Collection $settingsFields;

    public Collection $assets;
    public Collection $blocks;

    protected array $itemIDMap = [
        'assets'           => 'handle',
        'blocks'           => 'name',
        'adminPages'       => 'slug',
        'settingsSections' => 'id',
        'settings'         => 'name',
    ];

    public function __construct() {
        $this->installables     = collect([]);
        $this->integrations     = collect([]);

        $this->adminPages       = collect([]);
        $this->settingsSections = collect([]);
        $this->settings         = collect([]);
        $this->settingsFields   = collect([]);

        $this->assets           = collect([]);
        $this->blocks           = collect([]);
    }

    /**
     * Generic method to add an item to one of this object's collections.
     * Specific methods will be added for individual collections e.g. addPackage(), addSettingPage() etc.
     *
     * @param  string         $type The collection to add to e.g. 'settings', 'packages' etc.
     * @param  FeatureBuilder $item The item to add.
     * 
     * @return FeatureBuilder|null       The item that was added or retrieved if it already exists in the collection
     * @throws \InvalidArgumentException if the specified type does not correspond to a valid collection in the registry.
     */
    public function add(string $type, FeatureBuilder $item): FeatureBuilder {
        if (property_exists($this, $type)) {
            if (!$this->{$type}->contains($item)) {
                $this->{$type}->push($item);
                return $item;
            }

            else {
                $itemID = $this->itemIDMap[$type];
                return $this->get($type)
                        ->where($itemID, $item->{$itemID})
                        ->first();
            }
        }

        $plural = Str::plural($type); // Convert to plural form to check for collection property

        if (property_exists($this, $plural)) {
            if (!$this->{$plural}->contains($item)) {
                $this->{$plural}->push($item);
                return $item;
            }

            else {
                $itemID = $this->itemIDMap[$plural];
                return $this->get($plural)
                        ->where($itemID, $item->{$itemID})
                        ->first();
            }
        }

        throw new \InvalidArgumentException("Invalid registry type: {$type}");
    }

    /**
     * Generic method to get one of this object's collections.
     * Specific methods are also available below e.g. getSettings();
     *
     * @param  string $type The collection to get e.g. 'settings', 'packages' etc.
     * 
     * @return Collection                The requested collection
     * @throws \InvalidArgumentException if the specified type does not correspond to a valid collection in the registry.
     */
    public function get(string $type): Collection {
        if (property_exists($this, $type)) {
            return $this->{$type};
        }

        throw new \InvalidArgumentException("Invalid registry type: {$type}");
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
     * Returns the admin pages collection.
     *
     * @return Collection
     */
    public function getAdminPages(): Collection {
        return $this->adminPages;
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
     * Returns whether the registry contains any admin pages
     *
     * @return boolean
     */
    public function hasAdminPages(): bool {
        return $this->adminPages->isNotEmpty();
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
     * Returns whether the registry contains any installables
     *
     * @return boolean
     */
    public function hasInstallables(): bool {
        return $this->installables->isNotEmpty();
    }
}