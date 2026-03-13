<?php

namespace MM\Meros\App\Services\Theme;

use MM\Meros\App\Services\Theme\Concerns\HasContext;
use MM\Meros\App\Services\Theme\Concerns\HasFeatures;

use MM\Meros\App\Facades\Theme;

/**
 * Features should extend this class and define
 * the configure method().
 */
abstract class Package {
    /**
     * Whether the package is enabled.
     * Determines whether actions are taken in the initialise() method.
     * 
     * @var bool
     */
    public bool $enabled = true;

    /**
     * Determines whether the package is experimental.
     * 
     * @var bool
     */
    public bool $experimental = false;

    /**
     * Determines whether an 'enabled' option is available to
     * users via the WP dashboard.
     * 
     * @var bool
     */
    public bool $isSwitchable = true;

    use HasContext, HasFeatures;

    final public function __construct(
        string $name,
        string $path, 
        string $uri
    ) {
        // Set context
        $this->setContext($name, $path, $uri);

        if ($this->contextSet === true) {
            // Initialise the package
            $this->initialise();
        }
    }

    /**
     * Calls the boot method followed by the override method
     * for extensions.
     * 
     * @return void
     */
    private function initialise(): void {
        // Stop if the theme doesn't allow experimental features and this feature is experimental
        if (!Theme::allowsExperimentalFeatures() && $this->experimental) {
            $this->enabled = false;
            return;
        }

        // Create WP dashboard switch if isSwitchable is true
        if ($this->isSwitchable === true) {
            $this->createSwitch(
                'package',
                $this->name,
                'theme_features',
                'features',
                $this->description,
                $this->experimental,
                false
            );
        }

        // Stop if the package has been disabled in the WP dashboard
        $switch = get_option($this->enabledSettingName, '1');
        
        if (
            $this->isSwitchable === true &&
            $switch === '0'
        ) {
            $this->enabled = false;
            return;
        }

        // Call child loaders
        $this->addActions();
        $this->addFilters();
        $this->registerSettings();
        $this->registerInstallables();
        $this->loadFeatures();

        // Hook features into the Wordpress lifecycle
        $this->hookFeatures();
    }
}
