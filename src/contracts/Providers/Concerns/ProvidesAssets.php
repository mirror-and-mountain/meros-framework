<?php

namespace MM\Meros\Contracts\Providers\Concerns;

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
        return $this->resolveRequestFor(AssetGroup::class, $name);
    }

    /**
     * Resolves a specific script or scripts register based on the provided location and optional handle.
     *
     * @param string $location
     * @param string $handle
     *
     * @return Asset|Assets|null The requested script or the scripts register.
     * 
     * @throws \InvalidArgumentException If the provided location is invalid or does not correspond to a method in the implementing class.
     */
    final protected function scripts(string $location, string $handle = ''): Asset|Assets|null {
        $method = match ($location) {
            'site'     => 'frontendScripts',
            'frontend' => 'frontendScripts',
            'admin'    => 'adminScripts',
            'editor'   => 'editorScripts',
            default    => null,
        };

        if ($method === null || !method_exists($this, $method)) {
            throw new \InvalidArgumentException("Invalid location specified for scripts: location = {$location}");
        }

        return $this->{$method}($handle);
    }

    /**
     * Resolves a specific style or styles register based on the provided location and optional handle.
     *
     * @param string $location
     * @param string $handle
     *
     * @return Asset|Assets|null The requested style or the styles register.
     * 
     * @throws \InvalidArgumentException If the provided location is invalid or does not correspond to a method in the implementing class.
     */
    final protected function styles(string $location, string $handle = ''): Asset|Assets|null {
        $method = match ($location) {
            'site'     => 'frontendStyles',
            'frontend' => 'frontendStyles',
            'admin'    => 'adminStyles',
            'editor'   => 'editorStyles',
            default    => null,
        };

        if ($method === null || !method_exists($this, $method)) {
            throw new \InvalidArgumentException("Invalid location specified for styles: location = {$location}");
        }

        return $this->{$method}($handle);
    }

     /**
     * Retrieves a specific frontend script by handle or returns the scripts register.
     *
     * @param string $handle Optional. The handle of the script to retrieve.
     * 
     * @return Script|Scripts|null The requested script or the scripts register.
     */
    final protected function frontendScripts(string $handle = ''): Script|Scripts|null {
        return $this->resolveRequestFor(Script::class, $handle);
    }

    /**
     * Retrieves a specific frontend style by handle or returns the styles register.
     *
     * @param string $handle Optional. The handle of the style to retrieve.
     * 
     * @return Style|Styles|null The requested style or the styles register.
     */
    final protected function frontendStyles(string $handle = ''): Style|Styles|null {
        return $this->resolveRequestFor(Style::class, $handle);
    }

    /**
     * Retrieves a specific admin script by handle or returns the admin scripts register.
     *
     * @param string $handle Optional. The handle of the admin script to retrieve.
     * 
     * @return AdminScript|AdminScripts|null The requested admin script or the admin scripts register.
     */
    final protected function adminScripts(string $handle = ''): AdminScript|AdminScripts|null {
        return $this->resolveRequestFor(AdminScript::class, $handle);
    }

    /**
     * Retrieves a specific admin style by handle or returns the admin styles register.
     *
     * @param string $handle Optional. The handle of the admin style to retrieve.
     * 
     * @return AdminStyle|AdminStyles|null The requested admin style or the admin styles register.
     */
    final protected function adminStyles(string $handle = ''): AdminStyle|AdminStyles|null {
        return $this->resolveRequestFor(AdminStyle::class, $handle);
    }

    /**
     * Retrieves a specific editor script by handle or returns the editor scripts register.
     *
     * @param string $handle Optional. The handle of the editor script to retrieve.
     * 
     * @return EditorScript|EditorScripts|null The requested editor script or the editor scripts register.
     */
    final protected function editorScripts(string $handle = ''): EditorScript|EditorScripts|null {
        return $this->resolveRequestFor(EditorScript::class, $handle);
    }

    /**
     * Retrieves a specific editor style by handle or returns the editor styles register.
     *
     * @param string $handle Optional. The handle of the editor style to retrieve.
     * 
     * @return EditorStyle|EditorStyles|null The requested editor style or the editor styles register.
     */
    final protected function editorStyles(string $handle = ''): EditorStyle|EditorStyles|null {
        return $this->resolveRequestFor(EditorStyle::class, $handle);
    }
}