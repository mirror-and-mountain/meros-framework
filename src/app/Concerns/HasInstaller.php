<?php 

namespace MM\Meros\App\Concerns;

use MM\Meros\App\Support\Admin\Field;
use MM\Meros\App\Facades\Framework;

trait HasInstaller {
    /**
     * Generates the installer HTML for the package.
     *
     * @param string        $wrapperClass Optional CSS class for the wrapper div.
     * @param bool          $separateBtn Whether to return the button separately from the status area.
     * @return string|array The installer HTML or an array containing 'statusArea' and 'button' if $separateBtn is true.
     */
    private function makeInstaller(
        string $wrapperClass    = '', 
        string $statusAreaClass = 'meros-installer-info', 
        bool   $separateBtn     = false
    ): array|string {

        if (! $this->hasInstallables) {
            return '';
        }

        $merosInstalled = Framework::isServiceInstalled('core');

        $isInstalled   = !$merosInstalled ? false : $this->isInstalled();
        $installedTime = !$merosInstalled ? '' : $this->getInstalledTime();
        $hasUpdates    = !$merosInstalled ? false : $isInstalled && $this->hasUpdates();
        $updatedTime   = $hasUpdates ? $this->getUpdatedTime() : null;

        $isTheme = $this instanceof \MM\Meros\App\Theme;
        
        $statusArea = '<div class="' . esc_attr($wrapperClass) . '">';
        $button     = '';

        if (!$isInstalled) {
            $statusArea .= '<p id="' . esc_attr($this->handle) . '_install" class="' . esc_attr($statusAreaClass) . '">Install ' . esc_html($this->name) . ' to enable its features.</p>';

            $action = 'meros_install_feature';
            $id     = $this->handle;
            $label  = 'Install ' . $this->name;
            $data   = $isTheme ? ['installable' => 'theme'] : ['installable' => $this->handle];
            
            if ($separateBtn) {
                $button .= Field::makeButton(
                    $action,
                    $id,
                    $label,
                    true,
                    $data,
                    $action . '_' . ($isTheme ? 'theme' : $id)
                );
            } else {
                 $statusArea .= Field::makeButton(
                    $action,
                    $id,
                    $label,
                    true,
                    $data,
                    $action . '_' . ($isTheme ? 'theme' : $id)
                );
            }

            $statusArea .= '</div>';
        }

        else if ($hasUpdates) {
            $statusArea .= '<p class="' . esc_attr($statusAreaClass) . '">';
            $statusArea .= '<span id="' . esc_attr($this->handle) . '_install" class="' . esc_attr($statusAreaClass) . '">Installed: ' . esc_attr($installedTime) . '</span>';
            $statusArea .= '<span> | </span>';
            $statusArea .= '<span id="' . esc_attr($this->handle) . '_update" class="' . esc_attr($statusAreaClass) . '">Update Available';

            if ($installedTime !== $updatedTime) {
                    $statusArea .= ' (Last Updated: ' . esc_attr($updatedTime) . ')</span>';
            } else {
                $statusArea .= '</span>';
            }

            $statusArea .= '</p>';

            $action = 'meros_update_feature';
            $id     = $this->handle;
            $label  = 'Update ' . $this->name;
            $data   = $isTheme ? ['installable' => 'theme'] : ['installable' => $this->handle];

            if ($separateBtn) {
                $button .= Field::makeButton(
                    $action,
                    $id,
                    $label,
                    true,
                    $data,
                    $action . '_' . ($isTheme ? 'theme' : $id)
                );
            } else {
                 $statusArea .= Field::makeButton(
                    $action,
                    $id,
                    $label,
                    true,
                    $data,
                    $action . '_' . ($isTheme ? 'theme' : $id)
                );
            }

            $statusArea .= '</div>';
        }

        else if ($isInstalled && !$hasUpdates) {
            $statusArea .= '<p class="' . esc_attr($statusAreaClass) . '">';
            $statusArea .= '<span id="' . esc_attr($this->handle) . '_install" class="' . esc_attr($statusAreaClass) . '">Installed: ' . esc_attr($installedTime) . '</span>';

            if ($installedTime !== $updatedTime) {
                $statusArea .= '<span> | </span>';
                $statusArea .= '<span id="' . esc_attr($this->handle) . '_update" class="' . esc_attr($statusAreaClass) . '">Last Updated: ' . esc_attr($updatedTime) . '</span>';
            }

            $statusArea .= '</p>';
            $statusArea .= '</div>';
        }

        return $separateBtn ? [
            'statusArea' => $statusArea,
            'button'     => $button
        ] : $statusArea;
    }
}