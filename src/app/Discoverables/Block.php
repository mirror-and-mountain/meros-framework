<?php 

namespace MM\Meros\App\Discoverables;

use Closure;
use Illuminate\Support\Facades\File;

use MM\Meros\App\FeatureProvider;
use MM\Meros\App\Support\Feature;

use MM\Meros\App\Support\BlockVariation;

class Block extends Feature {
    public bool   $initialised = false;
    public bool   $enabled     = true;
    public string $name        = '';
    public string $path        = '';
    public array  $args        = [];

    protected string $initError = 'Block\'s make method must be called before using other configuration methods.';

    public function __construct(public FeatureProvider $source) {
        add_action('init', function () {
            $this->load($this);
        });
    }

    /**
     * Sets the block as ready (or not) based on the block's current configuration.
     * 
     * @return void
     */
    protected function setReady(): void {
        if (empty($this->name) || !empty($this->error)) {
            $this->ready = false;
            return;
        }

        $this->ready = true;
    }

    /**
     * Registers the block with WordPress via the 'init' hook.
     *
     * @param  Feature $instance
     *
     * @return void
     */
    protected function load(Feature $instance): void {
        if (! $instance->ready) {
            return;
        }

        $blockType = $instance->path !== '' ? $instance->path : $instance->name;

        register_block_type($blockType, $instance->args);
        $this->loaded = true;
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Configures the block using either a callback or by directly passing the block's name and args.
     *
     * @param  Closure|string $callbackOrName
     * @param  array|string   $argsOrPath
     *
     * @return self
     */
    public function make(Closure|string $callbackOrName = '', array|string $argsOrPath = []): self {
        // If a closure is passed, call it with the block instance to allow configuration within the closure.
        if ($callbackOrName instanceof Closure) {
            $this->initialised = true; // Allow using configuration methods within the closure.
            
            $callbackOrName($this);
            return $this;
        }

        $this->name = $callbackOrName;
        $this->args = is_array($argsOrPath) ? $argsOrPath : [];
        $this->path = is_string($argsOrPath) ? $argsOrPath : '';

        $this->setReady();
        $this->initialised = true;

        return $this;
    }

    /**
     * Allows for configuring the block using a callback after it has been created.
     *
     * @param Closure|null $callback
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function configure(?Closure $callback = null): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        if ($callback !== null) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Returns a new BlockVariation instance for defining a block variation, configured 
     * using either a callback or by directly passing the variation's name and args.
     *
     * @param Closure|string $callbackOrName
     * @param array          $args
     *
     * @return BlockVariation\
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function variation(Closure|string $callbackOrName = '', array $args = []): BlockVariation {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->initialised = false; // Prevent using further block configuration methods on the variation instance.

        $name = $callbackOrName instanceof Closure ? '' : $callbackOrName;
        
        $variation = app(BlockVariation::class, [
            'source' => $this, 
            'name'   => $name, 
            'args'   => $args
        ]);

        if ($callbackOrName instanceof Closure) {
            $callbackOrName($variation);
        }

        return $variation;
    }

    /**
     * Sets the name of the block. Should be passed in namespace/block-name format.
     *
     * @param string $name
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function name(string $name): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->name = $name;
        $this->setReady();
        return $this;
    }

    /**
     * Sets the path to a directory containing a block.json file.
     * Note this method will not check whether a block.json file exists in the directory.
     *
     * @param string $path
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function path(string $path): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        if (!File::exists($path) || !File::isDirectory($path)) {
            $this->error = "The specified path '{$path}' does not exist.";
            return $this;
        }
        
        $this->path = $path;

        $this->setReady();
        return $this;
    }

    // Chainable methods for setting the block type's arguments. 
    // Used mainly for registering blocks via PHP rather than block.json.

    /**
     * Sets the API version for the block.
     *
     * @param string $version
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function apiVersion(string $version): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['api_version'] = $version;
        return $this;
    }

    /**
     * Sets the title for the block.
     *
     * @param string $title
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function title(string $title): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['title'] = $title;
        return $this;
    }

    /**
     * Sets the description for the block.
     *
     * @param string $description
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function description(string $description): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['description'] = $description;
        return $this;
    }

    /**
     * Sets the text domain for the block.
     *
     * @param string $domain
     * 
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function domain(string $domain): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['text_domain'] = $domain;
        return $this;
    }

    /**
     * Sets the category of the block
     *
     * @param string $category
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function category(string $category): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['category'] = $category;
        return $this;
    }

    /**
     * Sets the icon of the block.
     *
     * @param string $icon
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function icon(string $icon): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['icon'] = $icon;
        return $this;
    }

    /**
     * Sets the keywords for the block.
     *
     * @param array $keywords
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function keywords(array $keywords): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['keywords'] = $keywords;
        return $this;
    }

    /**
     * Sets the parent block types that this block can be inserted into.
     *
     * @param array $parentBlocks
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function parent(array $parentBlocks): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['parent'] = $parentBlocks;
        return $this;
    }

    /**
     * Sets ancestor block types that this block can be inserted into.
     *
     * @param array $ancestorBlocks
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function ancestor(array $ancestorBlocks): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['ancestor'] = $ancestorBlocks;
        return $this;
    }

    /**
     * Sets the allowed child block types for this block.
     *
     * @param array $allowedBlocks
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function allowedBlocks(array $allowedBlocks): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['allowed_blocks'] = $allowedBlocks;
        return $this;
    }

    /**
     * Sets the context values this block provides to its descendants.
     *
     * @param array $providedContext
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function providesContext(array $providedContext): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['provides_context'] = $providedContext;
        return $this;
    }

    /**
     * Sets the context values this block consumes.
     *
     * @param array $usedContext
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function usesContext(array $usedContext): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['uses_context'] = $usedContext;
        return $this;
    }

    /**
     * Sets support flags and configuration for this block.
     *
     * @param array $supports
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function supports(array $supports): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['supports'] = $supports;
        return $this;
    }

    /**
     * Sets the block attributes schema.
     *
     * @param array $attributes
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function attributes(array $attributes): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['attributes'] = $attributes;
        return $this;
    }

    /**
     * Sets style variations for the block.
     *
     * @param array $styleVariations
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function styleVariations(array $styleVariations): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['styles'] = $styleVariations;
        return $this;
    }

    /**
     * Sets inline variations for the block.
     *
     * @param array $variations
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function variations(array $variations): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['variations'] = $variations;
        return $this;
    }

    /**
     * Sets CSS selectors used by block supports.
     *
     * @param array $selectors
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function selectors(array $selectors): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['selectors'] = $selectors;
        return $this;
    }

    /**
     * Sets a render callback for dynamic block rendering.
     *
     * @param callable|Closure $callback
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function render(callable|Closure $callback): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['render_callback'] = $this->convertToClosure($callback);
        return $this;
    }

    /**
     * Sets a callback used to lazily generate block variations.
     *
     * @param callable|Closure $callback
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function variationsCallback(callable|Closure $callback): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['variations_callback'] = $this->convertToClosure($callback);
        return $this;
    }

    /**
     * Sets frontend and editor script handles.
     *
     * @param array $scriptsHandles
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function scripts(array $scriptsHandles): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['script_handles'] = $scriptsHandles;
        return $this;
    }

    /**
     * Sets frontend and editor style handles.
     *
     * @param array $stylesHandles
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function styles(array $stylesHandles): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['style_handles'] = $stylesHandles;
        return $this;
    }

    /**
     * Sets editor-only script handles.
     *
     * @param array $editorScriptsHandles
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function editorScripts(array $editorScriptsHandles): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['editor_script_handles'] = $editorScriptsHandles;
        return $this;
    }

    /**
     * Sets editor-only style handles.
     *
     * @param array $editorStylesHandles
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function editorStyles(array $editorStylesHandles): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['editor_style_handles'] = $editorStylesHandles;
        return $this;
    }

    /**
     * Sets frontend-only script handles used on the block's rendered output.
     *
     * @param array $viewScriptsHandles
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function viewScripts(array $viewScriptsHandles): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['view_script_handles'] = $viewScriptsHandles;
        return $this;
    }

    /**
     * Sets frontend-only style handles used on the block's rendered output.
     *
     * @param array $viewStylesHandles
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function viewStyles(array $viewStylesHandles): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['view_style_handles'] = $viewStylesHandles;
        return $this;
    }

    /**
     * Sets block hook configuration for automatic placement relative to other blocks.
     *
     * @param array $hooks
     *
     * @return self
     * @throws \BadMethodCallException if the block has not been initialised with the make method.
     */
    public function hooks(array $hooks): self {
        if (!$this->initialised) {
            throw new \BadMethodCallException($this->initError);
        }

        $this->args['block_hooks'] = $hooks;
        return $this;
    }
}

