<?php 

namespace MM\Meros\Services;

use Closure;
use Illuminate\Support\Facades\File;

use MM\Meros\Services\Contracts\FeatureDefinition;

class Block extends FeatureDefinition {
    /**
     * The name of the block, in namespace/block-name format. Required for the block to be registered.
     *
     * @var string
     */
    public string $name = '';

    /**
     * Whether the block is enabled.
     *
     * @var boolean
     */
    protected bool $enabled = true;

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
     * Sets the block as ready (or not) based on the block's current configuration.
     * 
     * If the block is ready, it will be registered with WordPress via the 'init' hook in the load() method.
     * @return void
     */
    protected function hook(): void {
        if (empty($this->name)) {
            $this->ready = false;
            return;
        }

        $this->ready = true;

        if ($this->enabled) {
            add_action('init', function() {
                $this->load();
            });
        }
    }

    /**
     * Registers the block with WordPress via the 'init' hook.
     *
     * @return void
     */
    protected function load(): void { 
        $blockType = $this->path !== '' ? $this->path : $this->name;

        register_block_type($blockType, $this->args);
        $this->loaded = true;
    }

    /***************************
     * Public Chainable methods
     ***************************/

    /**
     * Sets the name of the block. Should be passed in namespace/block-name format.
     *
     * @param string $name
     *
     * @return self
     */
    public function name(string $name): self {
        $this->name = $name;
        $this->hook();
        return $this;
    }

    /**
     * Sets the path to a directory containing a block.json file.
     * Note this method will not check whether a block.json file exists in the directory.
     *
     * @param string $path
     *
     * @return self
     * @throws \InvalidArgumentException if the provided path does not exist or is not a directory.
     */
    public function path(string $path): self {

        if (!File::exists($path) || !File::isDirectory($path)) {
            throw new \InvalidArgumentException("The specified path '{$path}' does not exist.");
        }
        
        $this->path = $path;

        $this->hook();
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
     */
    public function apiVersion(string $version): self {
        $this->args['api_version'] = $version;
        return $this;
    }

    /**
     * Sets the title for the block.
     *
     * @param string $title
     *
     * @return self
     */
    public function title(string $title): self {
        $this->args['title'] = $title;
        return $this;
    }

    /**
     * Sets the description for the block.
     *
     * @param string $description
     *
     * @return self
     */
    public function description(string $description): self {
        $this->args['description'] = $description;
        return $this;
    }

    /**
     * Sets the text domain for the block.
     *
     * @param string $domain
     * 
     * @return self
     */
    public function domain(string $domain): self {
        $this->args['text_domain'] = $domain;
        return $this;
    }

    /**
     * Sets the category of the block
     *
     * @param string $category
     *
     * @return self
     */
    public function category(string $category): self {
        $this->args['category'] = $category;
        return $this;
    }

    /**
     * Sets the icon of the block.
     *
     * @param string $icon
     *
     * @return self
     */
    public function icon(string $icon): self {
        $this->args['icon'] = $icon;
        return $this;
    }

    /**
     * Sets the keywords for the block.
     *
     * @param array $keywords
     *
     * @return self
     */
    public function keywords(array $keywords): self {
        $this->args['keywords'] = $keywords;
        return $this;
    }

    /**
     * Sets the parent block types that this block can be inserted into.
     *
     * @param array $parentBlocks
     *
     * @return self
     */
    public function parent(array $parentBlocks): self {
        $this->args['parent'] = $parentBlocks;
        return $this;
    }

    /**
     * Sets ancestor block types that this block can be inserted into.
     *
     * @param array $ancestorBlocks
     *
     * @return self
     */
    public function ancestor(array $ancestorBlocks): self {
        $this->args['ancestor'] = $ancestorBlocks;
        return $this;
    }

    /**
     * Sets the allowed child block types for this block.
     *
     * @param array $allowedBlocks
     *
     * @return self
     */
    public function allowedBlocks(array $allowedBlocks): self {
        $this->args['allowed_blocks'] = $allowedBlocks;
        return $this;
    }

    /**
     * Sets the context values this block provides to its descendants.
     *
     * @param array $providedContext
     *
     * @return self
     */
    public function providesContext(array $providedContext): self {
        $this->args['provides_context'] = $providedContext;
        return $this;
    }

    /**
     * Sets the context values this block consumes.
     *
     * @param array $usedContext
     *
     * @return self
     */
    public function usesContext(array $usedContext): self {
        $this->args['uses_context'] = $usedContext;
        return $this;
    }

    /**
     * Sets support flags and configuration for this block.
     *
     * @param array $supports
     *
     * @return self
     */
    public function supports(array $supports): self {
        $this->args['supports'] = $supports;
        return $this;
    }

    /**
     * Sets the block attributes schema.
     *
     * @param array $attributes
     *
     * @return self
     */
    public function attributes(array $attributes): self {
        $this->args['attributes'] = $attributes;
        return $this;
    }

    /**
     * Sets style variations for the block.
     *
     * @param array $styleVariations
     *
     * @return self
     */
    public function styleVariations(array $styleVariations): self {
        $this->args['styles'] = $styleVariations;
        return $this;
    }

    /**
     * Sets inline variations for the block.
     *
     * @param array $variations
     *
     * @return self
     */
    public function variations(array $variations): self {
        $this->args['variations'] = $variations;
        return $this;
    }

    /**
     * Sets CSS selectors used by block supports.
     *
     * @param array $selectors
     *
     * @return self
     */
    public function selectors(array $selectors): self {
        $this->args['selectors'] = $selectors;
        return $this;
    }

    /**
     * Sets a render callback for dynamic block rendering.
     *
     * @param callable|Closure $callback
     *
     * @return self
     */
    public function render(callable|Closure $callback): self {
        $this->args['render_callback'] = $this->convertToClosure($callback);
        return $this;
    }

    /**
     * Sets a callback used to lazily generate block variations.
     *
     * @param callable|Closure $callback
     *
     * @return self
     */
    public function variationsCallback(callable|Closure $callback): self {
        $this->args['variations_callback'] = $this->convertToClosure($callback);
        return $this;
    }

    /**
     * Sets frontend and editor script handles.
     *
     * @param array $scriptsHandles
     *
     * @return self
     */
    public function scripts(array $scriptsHandles): self {
        $this->args['script_handles'] = $scriptsHandles;
        return $this;
    }

    /**
     * Sets frontend and editor style handles.
     *
     * @param array $stylesHandles
     *
     * @return self
     */
    public function styles(array $stylesHandles): self {
        $this->args['style_handles'] = $stylesHandles;
        return $this;
    }

    /**
     * Sets editor-only script handles.
     *
     * @param array $editorScriptsHandles
     *
     * @return self
     */
    public function editorScripts(array $editorScriptsHandles): self {
        $this->args['editor_script_handles'] = $editorScriptsHandles;
        return $this;
    }

    /**
     * Sets editor-only style handles.
     *
     * @param array $editorStylesHandles
     *
     * @return self
     */
    public function editorStyles(array $editorStylesHandles): self {
        $this->args['editor_style_handles'] = $editorStylesHandles;
        return $this;
    }

    /**
     * Sets frontend-only script handles used on the block's rendered output.
     *
     * @param array $viewScriptsHandles
     *
     * @return self
     */
    public function viewScripts(array $viewScriptsHandles): self {
        $this->args['view_script_handles'] = $viewScriptsHandles;
        return $this;
    }

    /**
     * Sets frontend-only style handles used on the block's rendered output.
     *
     * @param array $viewStylesHandles
     *
     * @return self
     */
    public function viewStyles(array $viewStylesHandles): self {
        $this->args['view_style_handles'] = $viewStylesHandles;
        return $this;
    }

    /**
     * Sets block hook configuration for automatic placement relative to other blocks.
     *
     * @param array $hooks
     *
     * @return self
     */
    public function hooks(array $hooks): self {
        $this->args['block_hooks'] = $hooks;
        return $this;
    }
}

