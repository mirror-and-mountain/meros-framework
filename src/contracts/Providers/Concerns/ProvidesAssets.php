<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Features\Assets\AssetGroup;
use MM\Meros\Registers\Assets\AssetGroups;

use MM\Meros\Contracts\Features\Assets\Asset;
use MM\Meros\Registers\Assets\Assets;

use MM\Meros\Contracts\Features\Assets\Script;
use MM\Meros\Contracts\Features\Assets\Style;

use MM\Meros\Contracts\Features\Assets\AdminScript;
use MM\Meros\Contracts\Features\Assets\AdminStyle;

use MM\Meros\Contracts\Features\Assets\EditorScript;
use MM\Meros\Contracts\Features\Assets\EditorStyle;

use MM\Meros\Registers\Assets\Scripts;
use MM\Meros\Registers\Assets\Styles;

use MM\Meros\Registers\Assets\AdminScripts;
use MM\Meros\Registers\Assets\AdminStyles;

use MM\Meros\Registers\Assets\EditorScripts;
use MM\Meros\Registers\Assets\EditorStyles;

use MM\Meros\Support\ClassInfo;

trait ProvidesAssets {
    use Abstracts;

    /**
     * Resolves a specific asset group or the asset groups register based on the provided name.
     *
     * @param string $name Optional. The name of the asset group to retrieve.
     *
     * @return AssetGroup|AssetGroups|null The requested asset group or the asset groups register.
     */
    final protected function assetGroups(string $name = ''): AssetGroup|AssetGroups|null {
        return $this->resolveFeatureRequestFor(AssetGroup::class, $name);
    }

    /**
     * Resolves a specific script or scripts register based on the provided location and optional handle.
     *
     * @param string $locationOrClass
     * @param string $handle
     *
     * @return Asset|Assets|null The requested script or the scripts register.
     * 
     * @throws \InvalidArgumentException If the provided location is invalid or does not correspond to a method in the implementing class.
     */
    final protected function scripts(string $locationOrClass = 'site', string $handle = ''): Asset|Assets|null {
        $location = $this->resolveAssetLocation($locationOrClass);
        $method   = $this->resolveAssetMethod($location, 'scripts');

        if ($this->looksLikeClass($locationOrClass)) {
            $handle = $locationOrClass;
        }

        return $this->{$method}($handle);
    }

    /**
     * Resolves a specific style or styles register based on the provided location and optional handle.
     *
     * @param string $locationOrClass
     * @param string $handle
     *
     * @return Asset|Assets|null The requested style or the styles register.
     * 
     * @throws \InvalidArgumentException If the provided location is invalid or does not correspond to a method in the implementing class.
     */
    final protected function styles(string $locationOrClass = 'site', string $handle = ''): Asset|Assets|null {
        $location = $this->resolveAssetLocation($locationOrClass);
        $method   = $this->resolveAssetMethod($location, 'styles');

        if ($this->looksLikeClass($locationOrClass)) {
            $handle = $locationOrClass;
        }

        return $this->{$method}($handle);
    }

    /**
     * Resolves the location of an asset based on the provided location or class name.
     *
     * @param string $locationOrClass The location or class name of the asset.
     *
     * @return string The resolved location of the asset ('site', 'admin', or 'editor').
     *
     * @throws \InvalidArgumentException If the provided class does not correspond to a valid asset type.
     */
    private function resolveAssetLocation(string $locationOrClass): string {
        if (Str::contains($locationOrClass, '\\')) {
            $class  = $locationOrClass;
            $parent = ClassInfo::get($class)->parent;
            
            return match ($parent) {
                Script::class       => 'site',
                AdminScript::class  => 'admin',
                EditorScript::class => 'editor',
                default => throw new \InvalidArgumentException("Invalid asset class provided: {$class}"),
            };
        }

        return $locationOrClass;
    }

    /**
     * Resolves the method name for retrieving assets based on the provided location.
     *
     * @param string $location The location of the asset ('site', 'frontend', 'admin', or 'editor').
     *
     * @return string The method name corresponding to the asset location.
     *
     * @throws \InvalidArgumentException If the provided location is invalid or does not correspond to a method in the implementing class.
     */
    private function resolveAssetMethod(string $location, string $type): string {
        $method = match ($location) {
            'site'     => 'frontend' . ucfirst($type),
            'frontend' => 'frontend' . ucfirst($type),
            'admin'    => 'admin' . ucfirst($type),
            'editor'   => 'editor' . ucfirst($type),
            default    => throw new \InvalidArgumentException("Invalid location specified for assets: location = {$location}"),
        };

        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("The method {$method} does not exist in the implementing class.");
        }

        return $method;
    }

     /**
     * Retrieves a specific frontend script by handle or returns the scripts register.
     *
     * @param string $handleOrClass Optional. The handle or class of the script to retrieve.
     * 
     * @return Script|Scripts|null The requested script or the scripts register.
     */
    final protected function frontendScripts(string $handleOrClass = ''): Script|Scripts|null {
        return $this->resolveFeatureRequestFor(Script::class, $handleOrClass);
    }

    /**
     * Retrieves a specific frontend style by handle or returns the styles register.
     *
     * @param string $handleOrClass Optional. The handle or class of the style to retrieve.
     * 
     * @return Style|Styles|null The requested style or the styles register.
     */
    final protected function frontendStyles(string $handleOrClass = ''): Style|Styles|null {
        return $this->resolveFeatureRequestFor(Style::class, $handleOrClass);
    }

    /**
     * Retrieves a specific site script by handle or returns the site scripts register.
     * Alias for frontendScripts().
     *
     * @param string $handleOrClass Optional. The handle or class of the script to retrieve.
     *
     * @return Script|Scripts|null
     */
    final protected function siteScripts(string $handleOrClass = ''): Script|Scripts|null {
        return $this->frontendScripts($handleOrClass);
    }

    /**
     * Retrieves a specific site style by handle or returns the site styles register.
     * Alias for frontendStyles().
     *
     * @param string $handleOrClass Optional. The handle or class of the style to retrieve.
     *
     * @return Style|Styles|null
     */
    final protected function siteStyles(string $handleOrClass = ''): Style|Styles|null {
        return $this->frontendStyles($handleOrClass);
    }

    /**
     * Retrieves a specific admin script by handle or returns the admin scripts register.
     *
     * @param string $handleOrClass Optional. The handle or class of the admin script to retrieve.
     * 
     * @return AdminScript|AdminScripts|null The requested admin script or the admin scripts register.
     */
    final protected function adminScripts(string $handleOrClass = ''): AdminScript|AdminScripts|null {
        return $this->resolveFeatureRequestFor(AdminScript::class, $handleOrClass);
    }

    /**
     * Retrieves a specific admin style by handle or returns the admin styles register.
     *
     * @param string $handleOrClass Optional. The handle or class of the admin style to retrieve.
     * 
     * @return AdminStyle|AdminStyles|null The requested admin style or the admin styles register.
     */
    final protected function adminStyles(string $handleOrClass = ''): AdminStyle|AdminStyles|null {
        return $this->resolveFeatureRequestFor(AdminStyle::class, $handleOrClass);
    }

    /**
     * Retrieves a specific editor script by handle or returns the editor scripts register.
     *
     * @param string $handleOrClass Optional. The handle or class of the editor script to retrieve.
     * 
     * @return EditorScript|EditorScripts|null The requested editor script or the editor scripts register.
     */
    final protected function editorScripts(string $handleOrClass = ''): EditorScript|EditorScripts|null {
        return $this->resolveFeatureRequestFor(EditorScript::class, $handleOrClass);
    }

    /**
     * Retrieves a specific editor style by handle or returns the editor styles register.
     *
     * @param string $handleOrClass Optional. The handle or class of the editor style to retrieve.
     * 
     * @return EditorStyle|EditorStyles|null The requested editor style or the editor styles register.
     */
    final protected function editorStyles(string $handleOrClass = ''): EditorStyle|EditorStyles|null {
        return $this->resolveFeatureRequestFor(EditorStyle::class, $handleOrClass);
    }
}