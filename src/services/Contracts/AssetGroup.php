<?php 

namespace MM\Meros\Services\Contracts;

use Illuminate\Support\Str;
use MM\Meros\Services\Contracts\FeatureDefinition;
use MM\Meros\Services\Concerns\IsSwitchable;

final class AssetGroup extends FeatureDefinition {
    /**
     * The unique name for the asset group.
     *
     * @var string
     */
    public string $name = '';

    /**
     * The label for the asset group, used in the admin interface.
     *
     * @var string
     */
    protected string $label = '';

    /**
     * A description of the asset group, providing additional context in the admin interface.
     *
     * @var string
     */
    protected string $description = '';

    /**
     * The assets that belong to this group.
     *
     * @var array<Asset>
     */
    protected array $assets = [];

    /**
     * Set to true as asset groups aren't queued via the queue() method.
     *
     * @var bool
     */
    protected bool $queued = true;

    use IsSwitchable;

    /***************************
     * Contract methods
     ***************************/

    protected function queue(): void {
        // Do nothing - not used for AssetGroups.
    }

    /***************************
     * Public Chainable Methods
     ***************************/

    /**
     * Sets the name of the asset group. If the label is not already set, it will be automatically generated from the name.
     *
     * @param string $name
     *
     * @return self
     */
    public function name(string $name): self {
        $this->name = Str::snake($name);

        if ($this->label === '') {
            $this->label = Str::title(Str::replace(['-', '_'], ' ', $name));
        }

        return $this;
    }

    /**
     * Sets the label for the asset group.
     *
     * @param string $label
     *
     * @return self
     */
    public function label(string $label): self {
        $this->label = $label;
        return $this;
    }

    /**
     * Sets the description for the asset group.
     *
     * @param string $description
     *
     * @return self
     */
    public function description(string $description): self {
        $this->description = $description;
        return $this;
    }

    /**
     * Adds an asset to the group.
     *
     * @param Asset $asset
     *
     * @return self
     */
    public function addAsset(Asset $asset): self {
        $this->assets[] = $asset;
        return $this;
    }

    /**
     * Queues assets in the group if the group is enabled.
     *
     * @return self
     */
    public function queueAssets(): self {
        $this->setIsEnabled();

        if ($this->isEnabled) {
            foreach ($this->assets as $asset) {
                $asset->groupQueue();
            }
        }

        return $this;
    }

    /***************************
     * Getters
     ***************************/

    /**
     * Gets the name of the asset group.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Gets the label of the asset group.
     *
     * @return string
     */
    public function getLabel(): string {
        if ($this->label === '') {
            return Str::title(Str::replace(['-', '_'], ' ', $this->name));
        }

        return $this->label;
    }

    /**
     * Gets the description of the asset group.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->description;
    }
}