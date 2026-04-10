<?php 

namespace MM\Meros\App\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

use MM\Meros\App\Features\Asset;

trait HasAssets {
    /**
     * Discovers asset files in the specified assets path and enqueues them.
     *
     * This method looks for JavaScript and CSS files in the configured assets
     * directory. For each valid asset file found, it creates an Asset instance.
     * 
     * Users can hook into the 'meros_register_asset_{handle}' action to modify the
     * asset before it is enqueued. 
     * 
     * The handle is derived from the asset's directory following patterns such as: 
     * 
     * '/assets/build/{group}/{location}/index.js' or
     * '/assets/build/{group}/{location}/style-index.css' 
     * 
     * ...where the Meros bundling system is used.
     * 
     * {group} may be omitted in the file structure, in which case the handle will 
     * be derived from the location only.
     *
     * @return void
     */
    protected function discoverAssets(): void {
        $assetsPath = $this->getPreference('assets_path');

        if (!File::exists($assetsPath) || !File::isDirectory($assetsPath)) {
            return;
        }

        $assetFiles = File::files($assetsPath);

        foreach ($assetFiles as $assetFile) {
            $path      = $assetFile->getPathname();
            $extension = $assetFile->getExtension();

            if (! in_array($extension, ['js', 'css'])) {
                continue; // Not a valid asset file
            }

            if ($extension === 'js') {
               $asset = $this->makeAsset()->script($path);
            }

            else {
                $asset = $this->makeAsset()->style($path);
            }

            do_action('meros_register_asset_' . $asset->handle, $asset);

            $asset->enqueue();
        }
    }    

    /**
     * Creates an Asset instance from the given config and registers it.
     *
     * @param  array $config Optional config array for the asset.
     * 
     * @return Asset The created Asset instance.
     */
    protected function makeAsset(array $config = []): Asset {        
        return app(
            Asset::class, [
                'source' => $this
            ]
        )->make($config);
    }

    /**
     * Returns array of asset objects registered by the item.
     * 
     * @param  bool $readyOnly Whether to return only assets that are ready.
     *
     * @return Collection
     */
    final public function getAssets(bool $readyOnly = false): Collection {
        if ($readyOnly) {
            return $this->registry->get('assets')
                    ->where('source', $this)
                    ->where('ready', true) ?? collect([]);
        } else {
            return $this->registry->get('assets')
                    ->where('source', $this) ?? collect([]);
        }
    }

    /**
     * Returns a specific asset object registered by the item.
     *
     * @param  string $handle The handle of the asset to return.
     * 
     * @return Asset|null
     */
    final public function getAsset(string $handle): Asset|null {
        $asset = $this->getAssets()->firstWhere('handle', $handle);

        return $asset ?: null;
    }
}