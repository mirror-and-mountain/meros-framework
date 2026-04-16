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

    public Collection $fieldGroups;
    public Collection $fields;
    public Collection $assets;
    public Collection $blocks;
    public Collection $blockVariations;

    protected array $itemIDMap = [
        'assets'           => 'handle',
        'blocks'           => 'name',
        'blockVariations'  => 'name',
        'adminPages'       => 'slug',
        'settingsSections' => 'id',
        'settings'         => 'name',
        'fieldGroups'      => 'slug',
        'fields'           => 'name',
    ];

    public function __construct() {
        $this->installables     = collect([]);
        $this->integrations     = collect([]);

        $this->adminPages       = collect([]);
        $this->settingsSections = collect([]);
        $this->settings         = collect([]);
        $this->settingsFields   = collect([]);

        $this->fieldGroups      = collect([]);
        $this->fields           = collect([]);
        $this->assets           = collect([]);
        $this->blocks           = collect([]);
        $this->blockVariations  = collect([]);
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
}