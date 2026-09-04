<?php 

namespace MM\Meros\Contracts\Features\Components;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Concerns\IsSwitchable;
use MM\Meros\Contracts\Features\Concerns\IsHookable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;

class Block extends Feature implements Makeable {
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

    use IsHookable, IsMakeable;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        $this->identifier('name', 'snake');
        $this->setHook('init', [$this, 'register']);
        $this->hook();
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    /**
     * Registers the block with WordPress via the 'init' hook.
     *
     * @return void
     */
    public function register(): void {
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

    public function path(string $path): static {
        $this->path = $path;
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
     * Gets the parent block types that this block can be inserted into.
     *
     * @return array
     */
    public function getParents(): array {
        return $this->args['parent'] ?? [];
    }
}

