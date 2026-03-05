<?php

namespace MM\Meros\Contracts;

use Illuminate\Support\Str;
use MM\Meros\Traits\Features\AssetManager;
use MM\Meros\Traits\Features\BlockManager;
use MM\Meros\Traits\Features\ComponentManager;
use MM\Meros\Traits\Features\SettingsManager;
use MM\Meros\Traits\Features\DatabaseManager;

/**
 * Features should extend this class and define
 * the configure method().
 */
abstract class Feature {
    /**
     * The instantiated theme manager. Used to bind
     * valid features to the object.
     * 
     * @var object
     */
    protected object $theme;

    /**
     * The type of feature. This is set automatically
     * based on the type of child class used.
     * 
     * @var string
     */
    public string $type = 'feature';

    /**
     * Whether the feature is enabled.
     * Determines whether actions are taken in the initialise() method.
     * 
     * @var bool
     */
    public bool $enabled = true;

    /**
     * Determines whether the feature is experimental.
     * 
     * @var bool
     */
    public bool $experimental = false;

    /**
     * Determines whether an 'enabled' option is available to
     * users via the WP dashboard.
     * 
     * @var bool
     */
    public bool $switchable = true;

    /**
     * Whether the feature's hook() method has been called.
     * 
     * @var bool
     */
    public bool $initialised = false;

    /**
     * The name of the feature in slug_format. Used in various
     * filter hooks.
     * 
     * @var string
     */
    protected string $name;

    /**
     * The feature's name including author_name_feature_name.
     * 
     * @var string
     */
    protected string $fullName;

    /**
     * The prefix for any hooks related to this feature.
     * 
     * @var string
     */
    protected string $hookPrefix;

    /**
     * The feature's description.
     * 
     * @var string
     */
    protected string $description = '';

    /**
     * The feature's path.
     * 
     * @var string
     */
    protected string $path;

    /**
     * The feature's URI.
     * 
     * @var string
     */
    protected string $uri;

    /**
     * The feature's author name.
     * 
     * @var string
     */
    protected string $authorName;

    /**
     * The feature's author description.
     * 
     * @var string
     */
    protected string $authorDesc;

    /**
     * The feature's author URL.
     * 
     * @var string
     */
    protected string $authorUrl;

    /**
     * The feature's author support URL.
     * 
     * @var string
     */
    protected string $authorSupportUrl;

    use AssetManager,
        BlockManager,
        ComponentManager,
        SettingsManager,
        DatabaseManager;

    public function __construct(
        object $theme, 
        ?string $name, 
        array $authorInfo, 
        string $path, 
        string $uri
    ) {
        // Set the theme object
        $this->theme = $theme;

        // Set the feature's name
        $this->setName($name);

        // Set the feature's path
        $this->path = trailingslashit($path);

        // Set the feature's URI
        $this->uri = trailingslashit($uri);

        // Set the feature's author info
        $this->setAuthorInfo($authorInfo);

        // Set the feature's full name
        $this->fullName = Str::slug($this->authorName, '_') . '_' . $this->name;

        // Set hook prefix
        $this->hookPrefix = Str::startsWith($this->fullName, 'meros')
            ? Str::after($this->fullName, 'meros_')
            : 'meros_' . $this->fullName;

        // Set up the feature
        $this->setUp();
    }

    /**
     * Sets the feature's name property.
     * 
     * @param string|null $name The name to set. If null, the class name is used.
     * @return void
     */
    private function setName(?string $name = null): void {
        if (isset($name)) {
            $name = Str::lower(Str::headline($name));
            $this->name = Str::slug($name, '_');
        } else {
            $class = Str::afterLast(get_class($this), '\\');
            $class = Str::lower(Str::headline($class));
            $this->name = Str::slug($class, '_');
        }
    }

    /**
     * Sets the feature's author information properties.
     * 
     * @param array $authorInfo
     * @return void
     */
    private function setAuthorInfo(array $authorInfo): void {
        if (! isset($this->authorName)) {
            $this->authorName = $authorInfo['name'] ?? 'Unknown';
        }

        if (! isset($this->authorDesc)) {
            $this->authorDesc = $authorInfo['description'] ?? '';
        }

        if (! isset($this->authorUrl)) {
            $this->authorUrl = $authorInfo['url'] ?? '';
        }

        if (! isset($this->authorSupportUrl)) {
            $this->authorSupportUrl = $authorInfo['support_url'] ?? '';
        }
    }

    /**
     * Calls the boot method followed by the override method
     * for extensions.
     * 
     * @return void
     */
    private function setUp(): void {
        // Stop if the theme doesn't allow experimental features and this feature is experimental
        if (!$this->theme->allowsExperimentalFeatures() && $this->experimental) {
            $this->enabled = false;
            return;
        }

        // Create WP dashboard switch if userSwitchable is true
        if ($this->switchable === true) {
            $this->createSwitch(
                'feature',
                $this->name,
                'theme_features',
                'features',
                $this->description,
                $this->experimental,
                false
            );
        }

        // Stop if the feature has been disabled in the WP dashboard
        $switch = get_option($this->featureEnabledSettingName, '1');
        if (
            $this->switchable === true &&
            $switch === '0'
        ) {
            $this->enabled = false;
            return;
        }

        // Call the boot method
        $this->boot();

        // If the feature is an extension, set the type and call override()
        if ($this instanceof Extension) {
            $this->type = 'extension';
            $this->override();
        }
    }

    /**
     * This method should be defined in the feature's main class
     * found in app/Features/<Feature> by default.
     *
     *
     * Where a feature is an extension, the extension package should
     * extend the Extension contract and define this method. Users
     * can then override any configuration using the Extension
     * contract's override() method which is called after boot().
     * 
     * @return void
     */
    abstract protected function boot(): void;

    /**
     * Prepares and hooks the feature's declared supports into
     * the Wordpress lifecycle.
     * 
     * @return void
     */
    final public function hook(): void {
        // Stop if the feature isn't enabled
        if ($this->enabled === false) {
            return;
        }

        if ($this->hasAssets) {
            $this->enqueueAssets();
        }

        if ($this->hasBlocks) {
            $this->registerBlocks();
        }

        if ($this->hasComponents) {
            $this->bindComponents();
            $this->bindViews();
            $this->theme->initialiseLivewire();
        }

        $this->initialised = true;
    }

    /**
     * This method is called after all features have been
     * hooked.
     * 
     * @return void
     */
    public function runAfterHook(): void {
        // Intentionally left blank for child classes to override.
    }

    /**
     * Returns the feature's author info.
     *
     * @return array|string
     */
    final public function getAuthorInfo(string $prop = ''): array|string {
        return match ($prop) {
            'name' => $this->authorName,
            'description' => $this->authorDesc,
            'url' => $this->authorUrl,
            'support_url' => $this->authorSupportUrl,
            default => [
                'name' => $this->authorName,
                'description' => $this->authorDesc,
                'url' => $this->authorUrl,
                'support_url' => $this->authorSupportUrl,
            ],
        };
    }

    /**
     * Returns either the feature's name or fullname if requested.
     * 
     * @param bool $full Whether to return the full name.
     * @return string
     */
    final public function getName(bool $full = false): string {
        return $full === false ? $this->name : $this->fullName;
    }
}
