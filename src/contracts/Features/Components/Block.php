<?php 

namespace MM\Meros\Services\Components;

use Illuminate\Support\Str;

use MM\Meros\Services\Contracts\FeatureDefinition;

use MM\Meros\Services\Concerns\IsHookable;
use MM\Meros\Services\Concerns\IsDiscoverable;
use MM\Meros\Services\Components\Concerns\IsSwitchable;

class Block extends FeatureDefinition {
    /**
     * The name of the block, in namespace/block-name format. Required for the block to be registered.
     *
     * @var string
     */
    public string $name = '';

    /**
     * The path to a directory containing a block.json file.
     *
     * @var string
     */
    protected string $path = '';

    /**
     * Arguments for registering the block type. Used when registering a block via PHP.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * Whether to automatically queue the block for registration on instantiation. 
     * Set to false as we need to set the enabled setting first if the block is switchable.
     *
     * @var bool
     */
    final protected bool $autoQueue = false;

    use IsSwitchable, IsDiscoverable, IsHookable;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        $this->name = $this->identifier;
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    /**
     * Queues the block for registration by hooking into WordPress' 'init' action.
     * 
     * @return void
     */
    protected function hook(): void {
        if (empty($this->name)) {
            return;
        }

        $this->setIsEnabled();

        if (!$this->hooked && $this->isEnabled) {
            add_action('init', function() {
                $this->register();
            });
        }

        $this->hooked = true;
    }

    /**
     * Registers the block with WordPress via the 'init' hook.
     *
     * @return void
     */
    protected function register(): void { 
        $blockType = $this->path !== '' ? $this->path : $this->name;

        register_block_type($blockType, $this->args);
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the parent block types that this block can be inserted into.
     *
     * @param array $parentBlocks
     *
     * @return static
     */
    public function parent(array $parentBlocks): static {
        $this->args['parent'] = $parentBlocks;
        return $this;
    }

    // =========================================================================
    // Attribute Getters
    // =========================================================================

    /**
     * Returns the name of the block.
     *
     * @return string
     */
    public function getName(bool $snake = false): string {
        if ($snake) {
            return Str::replace(['/', '-'], '_', $this->name);
        }

        return $this->name;
    }

    /**
     * Returns the description of the block, either from the block's arguments or from the block.json file if a path is set.
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->args['description'] ?? '';
    }

    /**
     * Gets the parent block types that this block can be inserted into.
     *
     * @return array
     */
    public function getParents(): array {
        return $this->args['parent'] ?? [];
    }
}

