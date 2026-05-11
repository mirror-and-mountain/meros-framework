<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;

class ViteAssets {
    /**
     * The parsed Vite manifest.
     *
     * @var array|null
     */
    protected ?array $manifest = null;

    /**
     * The path to the Vite manifest file.
     *
     * @var string
     */
    protected string $manifestPath;

    /**
     * The entry file for the Vite assets.
     *
     * @var string
     */
    protected string $entry;

    /**
     * The entry file path relative to the build directory, used for looking up in the manifest.
     *
     * @var string
     */
    protected string $shortEntry;

    /**
     * The build directory path.
     *
     * @var string
     */
    protected string $buildPath;

    /**
     * The build directory URL.
     *
     * @var string
     */
    protected string $buildUrl;
    
    /**
     * The URL of the Vite development server.
     *
     * @var string
     */
    protected string $devServerUrl = 'http://localhost:8000/toolbox/vite-dev';

    public function __construct() {
        // Do nothing...
    }

    /**
     * Determines if the application is running in development mode.
     *
     * @return bool
     */
    protected function isDev(): bool {
        return false; // Force production mode for now
        return getenv('MEROS_ENVIRONMENT') === 'true';
    }

    /**
     * Renders the appropriate script and link tags for the Vite assets based on the current environment.
     *
     * @param string $entry     The entry file for the Vite assets.
     * @param string $buildPath The build directory path for the Vite assets.
     *
     * @return string The rendered HTML for including the Vite assets.
     */
    public function render(string $entry, string $buildPath): string {
        $this->entry        = $entry;
        $this->buildPath    = $buildPath;
        $this->buildUrl     = trailingslashit(Str::replace(get_stylesheet_directory(), get_stylesheet_directory_uri(), $buildPath));
        $this->manifestPath = rtrim($buildPath, '/') . '/.vite/manifest.json';

        $this->shortEntry = Str::contains($this->entry, 'vendor')
            ? 'src/' . Str::after($this->entry, '/src/')
            : Str::after($this->entry, get_stylesheet_directory() . '/');

        if ($this->isDev()) {
            return $this->renderDev();
        }

        return $this->renderProduction();
    }

    /**
     * Renders the script tags for the Vite development server.
     *
     * @return string The rendered HTML for including the Vite assets from the development server.
     */
    protected function renderDev(): string {
        $devUrl = $this->devServerUrl;

        return <<<HTML
        <script type="module" src="{$devUrl}/@vite/client"></script>
        <script type="module" src="{$devUrl}/{$this->entry}"></script>
        HTML;
    }

    /**
     * Renders the script and link tags for the Vite production build.
     *
     * @return string The rendered HTML for including the Vite assets from the production build.
     */
    protected function renderProduction(): string {
        $html = '';

        foreach ($this->styles() as $css) {
            $html .= <<<HTML
            <link rel="stylesheet" href="{$css}">
            HTML;
        }

        $js = $this->asset();
        $html .= <<<HTML
        <script type="module" src="{$js}"></script>
        HTML;

        return $html;
    }

    /**
     * Returns the URL of the main JavaScript asset for the Vite entry.
     *
     * @return string The URL of the main JavaScript asset.
     */
    protected function asset(): string {
        $manifest = $this->getManifest();

        if (is_string($manifest)) {
            return $manifest; // Return error message if manifest couldn't be loaded
        }

        if (!isset($manifest[$this->shortEntry]) || !isset($manifest[$this->shortEntry]['file'])) {
            return "<!-- Vite entry [{$this->shortEntry}] not found in manifest. Run 'npm run build'. -->";
        }

        return $this->buildUrl . $manifest[$this->shortEntry]['file'];
    }

    /**
     * Returns the URLs of the CSS assets for the Vite entry.
     *
     * @return array The URLs of the CSS assets.
     */
    protected function styles(): array {
        $manifest = $this->getManifest();

        if (is_string($manifest)) {
            return []; // Return empty array if manifest couldn't be loaded
        }

        $css = $manifest[$this->shortEntry]['css'] ?? [];

        return array_map(fn($file) => $this->buildUrl . $file, $css);
    }

    /**
     * Returns the Vite manifest as an associative array.
     *
     * @return array|string The Vite manifest array or an error message if the manifest couldn't be loaded.
     */
    protected function getManifest(): array|string {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!file_exists($this->manifestPath)) {
            return "<!-- Vite manifest not found at {$this->manifestPath}. Run 'npm run build' first. -->";
        }

        return $this->manifest = json_decode(
            file_get_contents($this->manifestPath), true
        );
    }
}