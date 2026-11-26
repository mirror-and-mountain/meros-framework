<?php

namespace MM\Meros\Support;

use Illuminate\Support\Facades\File;
use MM\Meros\Facades\Framework;
use RuntimeException;

class FormAssets {
    public function scripts(): string {
        return $this->render('index.js', true);
    }

    public function styles(): string {
        return $this->render('style-index.css', false);
    }

    private function render(string $filename, bool $script): string {
        $framework = Framework::get();
        $html = [];

        // Dependencies must load before the main form bundle.
        foreach (['modal/', 'mforms/nice-forms/', 'mforms/tomselect/', 'mforms/quill/', 'mforms/'] as $directory) {
            $path = 'resources/assets/wordpress/build/' . $directory . $filename;
            $filePath = $framework->getPath($path);

            if (!File::isFile($filePath)) {
                throw new RuntimeException("Form asset '{$path}' is missing. Build the framework's WordPress assets first.");
            }

            if ($script) {
                $html[] = $this->scriptDependencies($framework->getPath(dirname($path) . '/index.asset.php'));
            }

            $version = File::hash($filePath);
            if ($version === false) {
                throw new RuntimeException("Unable to version form asset '{$path}'.");
            }

            $url = e($framework->getUri($path) . '?ver=' . $version);
            $html[] = $script
                ? '<script src="' . $url . '"></script>'
                : '<link rel="stylesheet" href="' . $url . '">';
        }

        return implode("\n", array_filter($html));
    }

    private function scriptDependencies(string $metadataPath): string {
        if (!File::isFile($metadataPath)) {
            throw new RuntimeException("Form asset metadata '{$metadataPath}' is missing. Build the framework's WordPress assets first.");
        }

        $metadata = File::getRequire($metadataPath);
        $dependencies = $metadata['dependencies'];
        if (!$dependencies) {
            return '';
        }

        $scripts = wp_scripts();
        ob_start();
        try {
            // WordPress also prints dependency initialization, such as lodash's noConflict alias.
            $scripts->do_items($dependencies);
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $missing = array_diff($dependencies, $scripts->done);
        if ($missing) {
            throw new RuntimeException('Unable to load form script dependencies: ' . implode(', ', $missing));
        }

        return $html;
    }
}
