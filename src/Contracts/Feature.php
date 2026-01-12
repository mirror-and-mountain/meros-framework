<?php

namespace MM\Meros\Contracts;

use Illuminate\Support\Str;
use MM\Meros\Traits\AssetManager;
use MM\Meros\Traits\BlockManager;
use MM\Meros\Traits\ComponentManager;
use MM\Meros\Traits\DatabaseManager;
use MM\Meros\Traits\FieldManager;
use MM\Meros\Traits\SettingsManager;

/**
 * Features should extend this class and define
 * the configure method().
 */
abstract class Feature
{
    /**
     * The instantiated theme manager. Used to bind
     * valid features to the object.
     */
    protected object $theme;

    /**
     * The type of feature. This is set automatically
     * based on the type of child class used.
     * 
     */
    public string $type = 'feature';

    /**
     * Whether the feature is enabled.
     * Determines whether actions are taken in the initialise() method.
     */
    public bool $enabled = true;

    /**
     * Determines whether the feature is experimental.
     */
    public bool $experimental = false;

    /**
     * Determines whether an 'enabled' option is available to
     * users via the WP dashboard.
     */
    public bool $switchable = true;

    /**
     * Whether the feature's initialised() method has been called.
     */
    public bool $initialised = false;

    /**
     * The name of the feature in slug_format. Used in various
     * filter hooks.
     */
    protected string $name;

    /**
     * The feature's name including author_name_feature_name.
     */
    protected string $fullName;

    /**
     * The feature's description.
     */
    protected string $description = '';

    /**
     * The feature's path.
     */
    protected string $path;

    /**
     * The feature's URI.
     */
    protected string $uri;

    /**
     * The feature's author information
     */
    protected string $authorName;

    protected string $authorDesc;

    protected string $authorUrl;

    protected string $authorSupportUrl;

    use AssetManager,
        BlockManager,
        ComponentManager,
        DatabaseManager,
        FieldManager,
        SettingsManager;

    public function __construct(object $theme, ?string $name, array $authorInfo, string $path, string $uri)
    {
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
        $this->fullName = Str::slug($this->authorName, '_').'_'.$this->name;

        // Set up the feature
        $this->setUp();
    }

    /**
     * Sets the feature's name property.
     */
    private function setName(?string $name = null): void
    {
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
     */
    private function setAuthorInfo(array $authorInfo): void
    {
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
     * Calls the configure method followed by the override method
     * for extensions.
     */
    private function setUp(): void
    {
        // Create WP dashboard switch if userSwitchable is true
        if ($this->switchable === true) {
            $this->createFeatureSwitchSetting(
                $this->description,
                $this->experimental
            );
        }

        // Stop if the feature has been disabled in the WP dashboard
        $switch = get_option($this->featureEnabledSettingName, '1');
        if ($this->switchable === true &&
             $switch === '0'
        ) {
            $this->enabled = false;

            return;
        }

        // Call the configure method
        $this->configure();

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
     * contract's override() method which is called after configure().
     */
    abstract protected function configure(): void;

    /**
     * Prepares and hooks the feature's declared supports into
     * the Wordpress lifecycle.
     */
    final public function initialise(): void
    {
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

        if ($this->hasFieldTypes) {
            $this->loadFields();
            $this->enqueueFieldAssets();
        }

        $this->initialised = true;
    }

    public function runAfterInitialise(): void
    {
        // Intentionally left blank for child classes to override.
    }

    /**
     * Returns the feature's author info.
     *
     * @return array
     */
    final public function getAuthorInfo(string $prop = ''): array|string
    {
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
     */
    final public function getName(bool $full = false): string
    {
        return $full === false ? $this->name : $this->fullName;
    }
}
