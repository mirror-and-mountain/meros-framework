<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait FieldManager
{
    /**
     * Indicates whether the feature has assets.
     */
    protected bool $hasFieldTypes = false;

    /**
     * Maps assets types/directories to Wordpress hooks.
     * Example: assets/build/admin/index.js will be enqueued using
     * admin_enqueue_scripts.
     */
    protected array $fieldAssetTypes = [
        'form_editor' => 'admin_enqueue_scripts',
        'form_render' => 'wp_enqueue_scripts',
    ];

    /**
     * The directory to search for assets in relative to the
     * feature directory.
     */
    protected string $fieldsDir = 'field-types/build';

    /**
     * Discovered scripts.
     */
    protected array $fieldScripts = [];

    /**
     * Discovered script conditions.
     */
    protected array $fieldScriptConditions = [];

    /**
     * Discovered script dependancies.
     */
    protected array $fieldScriptDeps = [];

    /**
     * Discovered styles.
     */
    protected array $fieldStyles = [];

    /**
     * Discovered style conditions.
     */
    protected array $fieldStyleConditions = [];

    /**
     * Discovered style dependancies.
     */
    protected array $fieldStyleDeps = [];

    /**
     * Scripts that have been registered using
     * wp_register_script.
     */
    protected array $registeredFieldScripts = [];

    /**
     * Styles that have been registered using
     * wp_register_style.
     */
    protected array $registeredFieldStyles = [];

    /**
     * Determines whether script handles should use
     * the feature's fullName. This can be useful if
     * the feature has a common name and we need to
     * avoid conflicts.
     */
    protected bool $useFullNameForFieldAssets = true;

    /**
     * Sets the absolute path and calls setAssets.
     * Continues to register discovered assets.
     */
    private function loadFields(): void
    {
        $assetsPath = $this->path.$this->fieldsDir;

        foreach ($this->fieldAssetTypes as $type => $_) {

            if ($this->fieldScripts[$type] ?? [] === []) {
                $this->setFieldAssets($assetsPath, $type, 'js');
            }

            if ($this->fieldStyles[$type] ?? [] === []) {
                $this->setFieldAssets($assetsPath, $type, 'css');
            }

        }

        $this->registerFieldAssets();
    }

    /**
     * Uses glob to search for assets using the given path, type and extension.
     * Sets asset handles to be used in wp_enqueue functions and updates the
     * scripts and styles properties. This method will also discover any
     * dependancies for each asset.
     */
    private function setFieldAssets(string $path, string $type, string $extension): void
    {
        if (! File::exists($path)) {
            return;
        }

        $typeMod = Str::replace('_', '-', $type);
        $assets = array_merge(
            File::glob("{$path}/*/{$typeMod}.{$extension}"),
            File::glob("{$path}/*/{$typeMod}-style.{$extension}")
        );

        if ($assets === []) {
            return;
        }

        $i = 0;
        foreach ($assets as $asset) {

            $pathInfo = pathinfo($asset);
            $conditionFile = trailingslashit($pathInfo['dirname']).$pathInfo['filename'].'.conditions.php';
            $dependancyFile = trailingslashit($pathInfo['dirname']).$pathInfo['filename'].'.asset.php';
            $name = $this->useFullNameForFieldAssets ? $this->fullName : $this->name;
            $handle = $name.'_'.basename($pathInfo['dirname']).'_'.$type.'_'.$i;

            if ($extension === 'js') {

                $dependencies = file_exists($dependancyFile) ? include $dependancyFile : [];
                $this->fieldScriptConditions[$type][$handle] = file_exists($conditionFile) ? include $conditionFile : [];
                $this->fieldScriptDeps[$type][$handle] = $dependencies['dependencies'] ?? [];
                $this->fieldScripts[$type][$handle] = Str::replace($this->path, $this->uri, $asset);

            } elseif ($extension === 'css') {

                $dependencies = file_exists($dependancyFile) ? include $dependancyFile : [];
                $this->fieldStyleConditions[$type][$handle] = file_exists($conditionFile) ? include $conditionFile : [];
                $this->fieldStyleDeps[$type][$handle] = $dependencies['dependencies'] ?? [];
                $this->fieldStyles[$type][$handle] = Str::replace($this->path, $this->uri, $asset);

            }

            $i++;
        }
    }

    /**
     * Registers discovered assets using wp_register_* functions.
     */
    private function registerFieldAssets(): void
    {
        add_action('init', function () {
            foreach ($this->fieldAssetTypes as $type => $_) {
                $i = 0;
                foreach ($this->fieldScripts[$type] ?? [] as $handle => $src) {
                    if (! is_string($handle)) {
                        $handle = "{$this->name}_{$type}_script_{$i}";
                    }

                    $registered = wp_register_script(
                        $handle,
                        $src,
                        $this->fieldScriptDeps[$type][$handle] ?? [],
                        filemtime(Str::replace($this->uri, $this->path, $src)),
                        false
                    );

                    if ($registered !== false) {
                        $this->registeredFieldScripts[$type][$handle] = $src;
                    }
                    $i++;
                }

                $i = 0;
                foreach ($this->fieldStyles[$type] ?? [] as $handle => $src) {
                    if (! is_string($handle)) {
                        $handle = "{$this->name}_{$type}_style_{$i}";
                    }

                    $registered = wp_register_style(
                        $handle,
                        $src,
                        $this->fieldStyleDeps[$type][$handle] ?? [],
                        filemtime(Str::replace($this->uri, $this->path, $src))
                    );

                    if ($registered !== false) {
                        $this->registeredFieldStyles[$type][$handle] = $src;
                    }
                    $i++;
                }
            }
        });
    }

    /**
     * Enqueues assets using wp_enqueue_* functions.
     */
    private function enqueueFieldAssets(): void
    {
        foreach ($this->fieldAssetTypes as $type => $hook) {
            add_action($hook, function () use ($type) {
                foreach ($this->registeredFieldScripts[$type] ?? [] as $handle => $_) {
                    $shouldEnqueue = true;

                    if ($type === 'form_editor') {
                        $shouldEnqueue = ($_GET['page'] ?? '') === 'meros-form-builder';
                    }

                    if ($shouldEnqueue) {
                        wp_enqueue_script($handle);
                    }
                }

                foreach ($this->registeredFieldStyles[$type] ?? [] as $handle => $_) {
                    $shouldEnqueue = true;

                    if ($type === 'form_editor') {
                        $shouldEnqueue = ($_GET['page'] ?? '') === 'meros-form-builder';
                    }

                    if ($shouldEnqueue) {
                        wp_enqueue_style($handle);
                    }
                }
                // Reset the hasAssets indicator depending on whether any assets have been discovered.
                $this->hasFieldTypes = $this->registeredFieldScripts !== [] || $this->registeredFieldStyles !== [];
            });
        }
    }
}
