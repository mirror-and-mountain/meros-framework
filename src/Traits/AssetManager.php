<?php

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait AssetManager {
    /**
     * Indicates whether the feature has assets.
     */
    private bool $hasAssets = false;

    /**
     * Maps assets locations/directories to Wordpress hooks.
     * Example: assets/build/admin/index.js will be enqueued using
     * admin_enqueue_scripts.
     */
    protected array $assetLocations = [
        'admin' => 'admin_enqueue_scripts',
        'editor' => 'enqueue_block_editor_assets',
        'site' => 'wp_enqueue_scripts',
    ];

    /**
     * The directory to search for assets in relative to the
     * feature directory.
     */
    protected string $assetsDir = 'assets/build';

    /**
     * Loaded scripts organised by location.
     */
    protected array $registeredScripts = [];

    /**
     * Loaded styles organised by location.
     */
    protected array $registeredStyles = [];

    /**
     * Determines whether script handles should use
     * the feature's fullName. This can be useful if
     * the feature has a common name and we need to
     * avoid conflicts.
     */
    protected bool $useFullNameForAssets = true;

    /**
     * Sets the absolute path and calls setAssets.
     */
    protected function loadAssets(bool $inFooter = false): void {
        $assetsPath = $this->path . $this->assetsDir;
        foreach ($this->assetLocations as $location => $_) {
            if ($this->scripts[$location] ?? [] === []) {
                $this->findAssets($assetsPath, $location, 'js');
            }

            if ($this->styles[$location] ?? [] === []) {
                $this->findAssets($assetsPath, $location, 'css');
            }
        }
    }

    /**
     * Uses glob to search for assets using the given path, location and extension.
     * Sets asset handles to be used in wp_enqueue functions and
     * registers assets to be enqueued.
     *
     * This method will also discover any dependencies for each asset.
     */
    private function findAssets(string $path, string $location, string $extension, bool $inFooter = false): void {
        if (! File::exists($path)) {
            return;
        }

        $assets = File::glob("{$path}/{$location}/*.{$extension}");
        if ($assets === []) {
            $assets = File::glob("{$path}/{$location}/**/*.{$extension}");
            if ($assets === []) {
                return;
            }
        }

        $i = 0;

        foreach ($assets as $asset) {
            $pathInfo = pathinfo($asset);
            $type = $extension === 'js' ? 'scripts' : 'styles';
            $conditionFile = trailingslashit($pathInfo['dirname']) . $pathInfo['filename'] . '.conditions.php';
            $dependancyFile = trailingslashit($pathInfo['dirname']) . $pathInfo['filename'] . '.asset.php';

            $handle = $this->generateHandle($asset, $type, $location, $i);
            $dependancies = File::exists($dependancyFile) ? include $dependancyFile : [];
            $conditions = File::exists($conditionFile) ? include $conditionFile : [];

            $this->addAsset($asset, $location, $handle, $dependancies['dependencies'] ?? [], $conditions, $inFooter, $i);
            $i++;
        }
    }

    /**
     * Registers an asset to be enqueued.
     */
    protected function addAsset(
        string $path,
        string $location,
        string $handle = '',
        array $dependencies = [],
        array $conditions = [],
        bool $inFooter = false,
        int $index = 0
    ): void {
        // Check the asset exists
        if (! File::exists($path)) {
            $path = $this->path . trailingslashit($this->assetsDir) . $path;
            if (! File::exists($path)) {
                return;
            }
        }

        // Check the location is valid
        if (! in_array($location, array_keys($this->assetLocations))) {
            return;
        }

        // Determine asset type
        $ext = File::extension($path);
        $type = $ext === 'js' ? 'scripts' : ($ext === 'css' ? 'styles' : '');
        if ($type === '') {
            return;
        }

        // Determine asset handle
        if ($handle === '') {
            $handle = $this->generateHandle($path, $type, $location, $index);
        }

        // Set SRC
        $src = Str::replace($this->path, $this->uri, $path);

        // Store the asset
        if ($type === 'scripts') {
            $this->registeredScripts[$location][$handle] = [
                'src' => $src,
                'dependencies' => $dependencies,
                'conditions' => $conditions,
                'version' => filemtime($path),
                'in_footer' => $inFooter,
            ];
        } else {
            $this->registeredStyles[$location][$handle] = [
                'src' => $src,
                'dependencies' => $dependencies,
                'conditions' => $conditions,
                'version' => filemtime($path),
            ];
        }

        $this->hasAssets = true;
    }

    /**
     * Enqueues registered assets using the appropriate hooks.
     */
    private function enqueueAssets(): void {
        foreach ($this->assetLocations as $location => $_) {
            foreach ($this->registeredScripts[$location] ?? [] as $handle => $properties) {
                $hook = $this->assetLocations[$location];
                $src = $properties['src'];
                $deps = $properties['dependencies'];
                $version = $properties['version'];
                $inFooter = $properties['in_footer'];

                add_action($hook, function () use ($location, $handle, $src, $deps, $version, $inFooter) {
                    $shouldEnqueue = $this->shouldEnqueueAsset('scripts', $location, $handle);
                    if ($shouldEnqueue) {
                        wp_enqueue_script(
                            $handle,
                            $src,
                            $deps,
                            $version,
                            $inFooter
                        );
                    }
                });
            }

            foreach ($this->registeredStyles[$location] ?? [] as $handle => $properties) {
                $hook = $this->assetLocations[$location];
                $src = $properties['src'];
                $deps = $properties['dependencies'];
                $version = $properties['version'];

                add_action($hook, function () use ($location, $handle, $src, $deps, $version) {
                    $shouldEnqueue = $this->shouldEnqueueAsset('styles', $location, $handle);
                    if ($shouldEnqueue) {
                        wp_enqueue_style($handle, $src, $deps, $version);
                    }
                });
            }
        }
    }

    /**
     * Generates a unique handle for an asset based on its
     * path, type, location and index.
     */
    private function generateHandle(string $path, string $type, string $location, int $index): string {
        $pathInfo = pathinfo($path);
        $inSubDir = Str::afterLast(rtrim(dirname($path), '/'), '/') !== $location;
        $name = $inSubDir
            ? Str::afterLast($pathInfo['dirname'], '/') . '_' . $pathInfo['filename']
            : $pathInfo['filename'];

        $name = $type . '_' . Str::replace('-', '_', $name);

        $featureName = $this->useFullNameForAssets ? $this->fullName : $this->name;

        return $featureName . '_' . $location . '_' . Str::replace('-', '_', $name) . '_' . $index;
    }

    /**
     * Determines whether an asset should be enqueued based on its
     * conditions.
     */
    private function shouldEnqueueAsset(string $type, string $location, string $handle): bool {
        $shouldEnqueue = true;
        $conditions = $type === 'scripts'
            ? $this->registeredScripts[$location][$handle]['conditions'] ?? []
            : $this->registeredStyles[$location][$handle]['conditions'] ?? [];

        if (is_array($conditions) && count($conditions) > 0) {
            switch ($location) {
                case 'admin':
                    $page = $_GET['page'] ?? '';
                    if (! in_array($page, $conditions)) {
                        $shouldEnqueue = false;
                    }
                    break;
                case 'site':
                    global $post;
                    if (isset($post)) {
                        $slug = $post->post_name;
                        if (! in_array($slug, $conditions)) {
                            $shouldEnqueue = false;
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $shouldEnqueue;
    }
}
