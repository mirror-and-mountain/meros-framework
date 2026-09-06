<?php 

namespace MM\Meros\Contracts\Features\Components;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Contracts\Feature;
use MM\Meros\Contracts\Features\Registrable;
use MM\Meros\Contracts\Features\Makeable;

use MM\Meros\Contracts\Features\Admin\SettingsContainer;
use MM\Meros\Registers\Admin\SettingsContainers;

use MM\Meros\Contracts\Concerns\IsSwitchable;
use MM\Meros\Contracts\Concerns\ResolvesPaths;
use MM\Meros\Contracts\Features\Concerns\IsRegistrable;
use MM\Meros\Contracts\Features\Concerns\IsMakeable;

use MM\Meros\Facades\Framework;

class Block extends Feature implements Registrable, Makeable {
    /**
     * The namespace component of the block's name. Automatically set to the provider's handle.
     *
     * @var string
     */
    private string $namespace = '';


    protected string $name = '';

    protected string $title = '';

    protected string $path = '';

    protected string|null $category = null;

    protected array|null $parent = null;

    protected array|null $ancestor = null;

    protected array|null $allowedBlocks = null;

    protected string|null $icon = null;

    protected array $keywords = [];

    protected string|null $textdomain = null;

    protected array $styles = [];

    protected array $variations = [];

    protected array $selectors = [];

    protected array|null $supports = null;

    protected array|null $example = null;

    protected mixed $renderCallback = null;

    protected mixed $variationCallback = null;

    protected array|null $attributes = null;

    protected array $usesContext = [];

    protected array|null $providesContext = null;

    protected array $blockHooks = [];

    protected array $editorScriptHandles = [];

    protected array $scriptHandles = [];

    protected array $viewScriptHandles = [];

    protected array $editorStyleHandles = [];

    protected array $styleHandles = [];

    protected array $viewStyleHandles = [];

    /**
     * Whether or not the block has been registered.
     *
     * @var boolean
     */
    private bool $registered = false;

    use ResolvesPaths, IsMakeable, IsRegistrable, IsSwitchable;

    // =========================================================================
    // Initialisation
    // =========================================================================

    protected function init(): void {
        $this->identifier('name', 'slug');
        $this->namespace = $this->getProvider()->getHandle(true) . '/';
    }

    /**
     * Resolves the settings container for the asset group, which holds its switch setting.
     *
     * @param SettingsContainers $register
     *
     * @return SettingsContainer
     */
    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        $container = $register->get('meros_blocks_settings', Framework::get());

        if ($container === null) {
            $container = $register->checkout(Framework::get())->makeFrom('meros_blocks_settings');
        }

        if (!($container instanceof SettingsContainer)) {
            throw new \LogicException("The resolved settings container must be an instance of SettingsContainer.");
        }

        return $container;
    }

    protected function beforeSwitchInit(): void {
        if ($this->name === '' && !in_array(static::class, [Block::class, DynamicBlock::class])) {
            $this->name(Str::snake(class_basename($this)));
        }
    }

    // =========================================================================
    // Hooking
    // =========================================================================

    protected function whenEnabled(): void {
        if ($this->registered === false) {
            add_action('init', function () {
                $isDynamic = $this->path === '';
                $blockType = $isDynamic ? $this->getName() : $this->path;

                register_block_type($blockType, $isDynamic ? $this->getArgs() : []);
            });
        }

        $this->registered = true;
    }

    /**
     * Converts this classes properties to a valid args array for the register_block_type method.
     *
     * @return array
     */
    private function getArgs(): array {
        $args = [
            'title',
            'category',
            'parent',
            'ancestor',
            'allowed_blocks',
            'icon',
            'description',
            'keywords',
            'textdomain',
            'styles',
            'variations',
            'selectors',
            'supports',
            'example',
            'render_callback',
            'variation_callback',
            'attributes',
            'uses_context',
            'provides_context',
            'block_hooks',
            'editor_script_handles',
            'script_handles',
            'view_script_handles',
            'editor_style_handles',
            'style_handles',
            'view_style_handles'
        ];

        $parsedArgs = [];
        foreach ($args as $arg) {
            $camel = Str::camel($arg);

            if (property_exists($this, $camel)) {
                if (isset($this->{$camel})) {
                    $parsedArgs[$arg] = $this->{$camel};
                }
            } 
        }

        return $parsedArgs;
    }

    // =========================================================================
    // Attribute Setters
    // =========================================================================

    /**
     * Sets the block's name.
     *
     * @param string $name
     *
     * @return static
     */
    final public function name(string $name): static {
        return $this->setIdentifier($name, false);
    }

    /**
     * Sets the path to a valid block.json file.
     *
     * @param string  $path
     * @param boolean $configure Optional. Configure the block using the given path and its block.json file. Defaults to true.
     *
     * @return static
     */
    final public function path(string $path, bool $configure = true): static {
        if ($configure) {
            $this->configureFromPath($path);
            return $this;
        }

        $this->path = $path;
        return $this;
    }

    /**
     * Sets the human-readable label for the block.
     *
     * @param string $title
     *
     * @return static
     */
    final public function title(string $title): static {
        $this->title = $title;
        return $this;
    }

    /**
     * Sets the block's category.
     *
     * @param string $category
     *
     * @return static
     */
    final public function category(string $category): static {
        $this->category = $category;
        return $this;
    }

    /**
     * Adds parent blocks to the block's parents array.
     *
     * @param array<string> $parents
     *
     * @return static
     */
    final public function parent(array $parents): static {
        $this->parent = $parents;
        return $this;
    }

    /**
     * Adds ancestor blocks to the block's ancestor array.
     *
     * @param array<string> $ancestors
     *
     * @return static
     */
    final public function ancestor(array $ancestors): static {
        $this->ancestor = $ancestors;
        return $this;
    }

    /**
     * Adds allowed blocks to the block's allowedBlocks array.
     *
     * @param array<string> $allowedBlocks
     *
     * @return static
     */
    final public function allowedBlocks(array $allowedBlocks): static {
        $this->allowedBlocks = $allowedBlocks;
        return $this;
    }

    /**
     * Adds an icon to the block in the block editor.
     *
     * @param string $icon
     *
     * @return static
     */
    final public function icon(string $icon): static {
        $this->icon = $icon;
        return $this;
    }

    /**
     * Adds keywords used for search in the block editor.
     *
     * @param array<string> $keywords
     *
     * @return static
     */
    final public function keywords(array $keywords): static {
        $this->keywords = $keywords;
        return $this;
    }

    /**
     * Sets the block's textdomain.
     *
     * @param string $textdomain
     *
     * @return static
     */
    final public function textdomain(string $textdomain): static {
        $this->textdomain = $textdomain;
        return $this;
    }

    /**
     * Adds styles to the block's styles array.
     *
     * @param array $styles
     *
     * @return static
     */
    final public function styles(array $styles): static {
        $this->styles = $styles;
        return $this;
    }

    /**
     * Adds variations to the block's variations array.
     *
     * @param array $variations
     *
     * @return static
     */
    final public function variations(array $variations): static {
        $this->variations = $variations;
        return $this;
    }

    /**
     * Adds custom css selectors to the block.
     *
     * @param array<string> $selectors
     *
     * @return static
     */
    final public function selectors(array $selectors): static {
        $this->selectors = $selectors;
        return $this;
    }

    /**
     * Adds supports to the block's supports array.
     *
     * @param array $supports
     *
     * @return static
     */
    final public function supports(array $supports): static {
        $this->supports = $supports;
        return $this;
    }

    /**
     * Adds a single support to the block's supports array.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return static
     */
    final public function support(string $key, mixed $value): static {
        $this->supports[$key] = $value;
        return $this;
    }

    /**
     * Sets an example configuration for the block.
     *
     * @param array $example
     *
     * @return static
     */
    final public function example(array $example): static {
        $this->example = $example;
        return $this;
    }

    /**
     * Sets the block's render callback.
     *
     * @param callable $renderCallback
     *
     * @return static
     */
    final public function renderCallback(callable $renderCallback): static {
        $this->renderCallback = $renderCallback;
        return $this;
    }

    /**
     * Sets the block's variation callback.
     *
     * @param callable $variationCallback
     *
     * @return static
     */
    final public function variationCallback(callable $variationCallback): static {
        $this->variationCallback = $variationCallback;
        return $this;
    }

    /**
     * Adds attributes to the block's attributes array.
     *
     * @param array $attributes
     *
     * @return static
     */
    final public function attributes(array $attributes): static {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * Adds a single attribute to the block's attributes array.
     *
     * @param string $key
     * @param mixed  $value
     * @param bool   $merge
     *
     * @return static
     */
    final public function attribute(string $key, mixed $value, bool $merge = true): static {
        if (is_array($this->attributes[$key] ?? null) && is_array($value) && $merge) {
            $this->attributes[$key] = array_merge($this->attributes[$key], $value);
        }

        else {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Adds context to the block's uses_context array.
     *
     * @param array<string> $usesContext
     *
     * @return static
     */
    final public function usesContext(array $usesContext): static {
        $this->usesContext = $usesContext;
        return $this;
    }

    /**
     * Adds context to the block's provides_context array.
     *
     * @param array<string>|null $providesContext
     *
     * @return static
     */
    final public function providesContext(?array $providesContext): static {
        $this->providesContext = $providesContext;
        return $this;
    }

    /**
     * Adds block hooks.
     *
     * @param array $blockHooks
     *
     * @return static
     */
    final public function blockHooks(array $blockHooks): static {
        $this->blockHooks = $blockHooks;
        return $this;
    }

    /**
     * Adds script handles to the block's editor_script_handles array.
     *
     * @param array<string> $editorScriptHandles
     *
     * @return static
     */
    final public function editorScriptHandles(array $editorScriptHandles): static {
        $this->editorScriptHandles = $editorScriptHandles;
        return $this;
    }

    /**
     * Adds a single editor script handle to the block.
     *
     * @param string $handle
     *
     * @return static
     */
    final public function editorScript(string $handle): static {
        if (!in_array($handle, $this->editorScriptHandles)) {
            $this->editorScriptHandles[] = $handle;
        }

        return $this;
    }

    /**
     * Adds script handles to the block's script_handles array.
     *
     * @param array<string> $scriptHandles
     *
     * @return static
     */
    final public function scriptHandles(array $scriptHandles): static {
        $this->scriptHandles = $scriptHandles;
        return $this;
    }

    /**
     * Adds a single script handle to the block.
     *
     * @param string $handle
     *
     * @return static
     */
    final public function script(string $handle): static {
        if (!in_array($handle, $this->scriptHandles)) {
            $this->scriptHandles[] = $handle;
        }

        return $this;
    }

    /**
     * Adds script handles to the block's view_script_handles array.
     *
     * @param array<string> $viewScriptHandles
     *
     * @return static
     */
    final public function viewScriptHandles(array $viewScriptHandles): static {
        $this->viewScriptHandles = $viewScriptHandles;
        return $this;
    }

    /**
     * Adds a single view script handle to the block.
     *
     * @param string $handle
     *
     * @return static
     */
    final public function viewScript(string $handle): static {
        if (!in_array($handle, $this->viewScriptHandles)) {
            $this->viewScriptHandles[] = $handle;
        }

        return $this;
    }

    /**
     * Adds style handles to the block's editor_styles_handles array.
     *
     * @param array<string> $editorStyleHandles
     *
     * @return static
     */
    final public function editorStyleHandles(array $editorStyleHandles): static {
        $this->editorStyleHandles = $editorStyleHandles;
        return $this;
    }

    /**
     * Adds a single editor style handle to the block.
     *
     * @param string $handle
     *
     * @return static
     */
    final public function editorStyle(string $handle): static {
        if (!in_array($handle, $this->editorStyleHandles)) {
            $this->editorStyleHandles[] = $handle;
        }

        return $this;
    }

    /**
     * Adds style handles to the block's styles_handles array.
     *
     * @param array<string> $styleHandles
     *
     * @return static
     */
    final public function styleHandles(array $styleHandles): static {
        $this->styleHandles = $styleHandles;
        return $this;
    }

    /**
     * Adds a single style handle to the block.
     *
     * @param string $handle
     *
     * @return static
     */
    final public function style(string $handle): static {
        if (!in_array($handle, $this->styleHandles)) {
            $this->styleHandles[] = $handle;
        }

        return $this;
    }

    /**
     * Adds style handles to the block's view_styles_handles array.
     *
     * @param array<string> $viewStyleHandles
     *
     * @return static
     */
    final public function viewStyleHandles(array $viewStyleHandles): static {
        $this->viewStyleHandles = $viewStyleHandles;
        return $this;
    }

    /**
     * Adds a single view style handle to the block.
     *
     * @param string $handle
     *
     * @return static
     */
    final public function viewStyle(string $handle): static {
        if (!in_array($handle, $this->viewStyleHandles)) {
            $this->viewStyleHandles[] = $handle;
        }

        return $this;
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Returns the block's name. If full is set to true (default: true), then the block's full name
     * including namespace will be returned.
     *
     * @param boolean $full
     *
     * @return string
     */
    final public function getName(bool $full = true): string {
        if ($full) {
            if (Str::contains($this->name, '/')) {
                return $this->name;
            }

            return $this->namespace . $this->name;
        }

        return $this->name;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Resolves and sets the block's file path (a directory path containing a block.json file).
     * Additionally, this method sets the block's parents for switching if any are discovered.
     *
     * @param string $path
     *
     * @return void
     */
    private function configureFromPath(string $path): void {
        $blockJsonPath = $this->resolveBlockPath($path);
        $this->path = dirname($blockJsonPath);

        $blockJson = File::json($blockJsonPath);

        if (is_array($blockJson) && 
            array_key_exists('parent', $blockJson) &&
            is_array($blockJson['parent'])
        ) {
            $this->parent($blockJson['parent']);
        }

    }

    /**
     * Resolves the block's path.
     *
     * @param string $path
     *
     * @return string
     */
    private function resolveBlockPath(string $path): string {
        if ($this->pathLooksAbsolute($path) && 
            $this->pathIsDirectory($path)
        ) {
            $blockJsonPath = $this->getDirectoryFile($path, 'block.json');
            if ($blockJsonPath !== null) {
                return $blockJsonPath;
            }
        }

        $provider = $this->getProvider();
        $providerBlocksPath = $provider->getPreference('blocks_path');

        $path = rtrim($providerBlocksPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        if (!$this->pathIsDirectory($path)) {
            throw new \InvalidArgumentException("The provided path '{$path}' does not point to a valid directory.");
        }

        $blockJsonPath = $this->getDirectoryFile($path, 'block.json');

        if ($blockJsonPath === null) {
            throw new \InvalidArgumentException("Couldn't locate a valid block.json file at the given path: '{$path}'");
        }

        return $blockJsonPath;
    }
}

