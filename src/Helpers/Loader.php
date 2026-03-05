<?php

namespace MM\Meros\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MM\Meros\Contracts\Extension;
use MM\Meros\Contracts\Feature;

/**
 * Provides utilities for loading the theme's
 * features, extensions and plugins.
 */
class Loader {
    /**
     * The namespace for extensions.
     * 
     * @var string
     */
    public string $extensionsNamespace;

    /**
     * The extensions defined in the theme config.
     * 
     * @var array
     */
    public array $extensions;

    /**
     * The features defined in the theme config.
     * 
     * @var array
     */
    public array $features;

    /**
     * The default author info for features/extensions.
     * 
     * @var array
     */
    public array $defaultAuthorInfo;

    /**
     * The instantiated theme manager. Used to bind
     * valid features to the object.
     * 
     * @var object
     */
    public object $theme;

    /**
     * Collects and sets feature parameters from the theme
     * config file located in config/theme.php. Returns an
     * instance of the Loader for further inspection and usage
     * by the caller.
     * 
     * @param object $theme The theme manager instance.
     * @return self The Loader instance.
     */
    public static function init(object $theme): self {
        $instance = new self;
        $instance->theme = $theme;
        $featuresConfig = base_path('config/features.php');

        // Set the theme's default author info
        $instance->defaultAuthorInfo = $instance->theme->getAuthorInfo();

        // Check the theme config file exists
        if (File::exists($featuresConfig)) {
            $instance->extensionsNamespace = Config::get('features.extensions_namespace') ?? 'App\\Extensions';
            $instance->extensions = Config::get('features.extensions') ?? [];
            $instance->features = Config::get('features.features') ?? [];
        }

        return $instance;
    }

    /**
     * Loads features of the given type. This includes validating then
     * instantiating each feature's class and adding them to the theme's
     * feature array.
     * 
     * @param string $type The type of feature to load.
     * @return void
     */
    public function load(string $type): void {
        $extensionDefs = [];
        $baseClass = '';

        switch ($type) {
            case 'extensions':
                $extensionDefs = $this->extensions;
                $baseClass = Extension::class;
                break;

            case 'features':
                $extensionDefs = $this->features ?? [];
                $baseClass = Feature::class;
                break;
        }

        if ($extensionDefs === [] || $baseClass === '') {
            return;
        }

        // Validate and load each feature of the given type
        foreach ($extensionDefs as $class) {
            $this->loadItem($class, $baseClass);
        }
    }

    /**
     * Validates and loads individual features, binding them to
     * the theme's main class.
     *
     * @param  string  $extPath
     * @param  string|array  $file
     * @return void
     */
    private function loadItem(
        string $class,
        string $baseClass
    ): void {
        $class = ClassInfo::get($class);

        $feature = false;

        // Check the main feature class exists/is loadable
        if (
            ! $class ||
            ! $class->isDescendantOf($baseClass)
        ) {
            return;
        }

        switch ($baseClass) {
            case Extension::class:
                if ($class->namespace === $this->extensionsNamespace) {
                    $parent = ClassInfo::get($class->parent);
                    $featurePath = $parent->path;
                    $featureUri = $parent->uri;
                } else {
                    $featurePath = $class->path;
                    $featureUri = $class->uri;
                }

                // Instantiate the extension feature
                $feature = Features::instantiate(
                    $this->theme,
                    $class->name,
                    $featurePath,
                    $featureUri,
                    $this->defaultAuthorInfo
                );

                break;

            case Feature::class:
                $featureName = Str::afterLast(Str::beforeLast($class->name, '\\'), '\\');
                $featurePath = $class->path;
                $featureUri = $class->uri;

                // Instantiate the feature
                $feature = Features::instantiate(
                    $this->theme,
                    $class->name,
                    $featurePath,
                    $featureUri,
                    $this->defaultAuthorInfo,
                    $featureName
                );

                break;
        }

        // Check that the feature has been instantiated
        if ($feature === false) {
            return;
        }

        $author = Str::slug($feature->getAuthorInfo('name'), '_');
        $name = $feature->getName();

        $featureName = $author . '.' . $name;

        // Bind the feature to the theme manager class
        $this->theme->addFeature($featureName, $feature);
    }
}
