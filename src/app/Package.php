<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Contracts\Features\Admin\MenuPage;
use MM\Meros\Contracts\Features\Data\Table;

use MM\Meros\Contracts\Providers\Concerns\IsNonFrameworkProvider;

use MM\Meros\Support\ClassInfo;
use MM\Meros\Facades\Framework;

abstract class Package extends Provider {
    use IsNonFrameworkProvider;

    /**
     * Indicates whether the package is enabled.
     *
     * @var boolean
     */
    private bool $enabled;
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function afterInit(): void {
        $info = ClassInfo::get(static::class);

        $this->setName(Str::headline($info->shortName));
        $this->setPath($info->path);
        $this->setUri($info->uri);
        $this->registerTables();
    }

    /**
     * Sets the package's handle.
     *
     * @param string $handle
     *
     * @return void
     */
    final protected function setHandle(string $handle): void {
        $handle = Str::snake($handle);
        $author = $this->getAuthor();

        parent::setHandle(
            Str::startsWith($handle, 'meros_') 
                ? $handle 
                : ($author !== '' ? $author . '_' . $handle : $handle)
        );
    }

    // =========================================================================
    // Settings Management
    // =========================================================================

    /**
     * Resolves the settings container for the package.
     *
     * @param SettingsContainers $register The SettingsContainers register.
     *
     * @return SettingsContainer The settings container for the package.
     */
    final public function resolveSettingsContainer(SettingsContainers $register): SettingsContainer {
        $containerName = Str::startsWith($this->getHandle(), 'meros_') 
            ? $this->getHandle() . '_settings' 
            : 'meros_' . $this->getHandle() . '_settings';

        $menuPageSlug = $this->getHandle(true);
        $menuPage = $this->initSettingsPage($menuPageSlug)->getSlug();

        return $register->get($containerName, $this) ?? 
               $register
                ->checkout($this)
                ->make(function ($container) use ($containerName, $menuPage) {
                    $container->name($containerName);
                    $container->label($this->getName() . ' Settings');
                    $container->description('Settings for the ' . $this->getName() . ' package.');
                    $container->page($menuPage);
                });
    }

    /**
     * Initialises and returns the settings page for the package.
     *
     * @param string $slug
     *
     * @return MenuPage
     */
    private function initSettingsPage(string $slug): MenuPage {
        $packageSettingsPage = $this->menuPages()->get('meros-packages');

        if ($packageSettingsPage === null) {
            $packageSettingsPage = $this->menuPages()->makeFrom('meros-packages');
        }

        if (!($packageSettingsPage instanceof MenuPage)) {
            throw new \RuntimeException('The "meros-packages" menu page must be an instance of MenuPage.');
        }

        $settingsPage = $packageSettingsPage->getSubPage($slug);

        if ($settingsPage === null) {
            $settingsPage = $packageSettingsPage->subpage(function (MenuPage $page) use ($slug) {
                $page->slug($slug);
                $page->title($this->getName() . ' Settings');

                if ($this->getDescription() !== '') {
                    $page->callback(function () use ($slug) {
                        echo '<nav class="meros-breadcrumbs" aria-label="Breadcrumb">
                            <a href="' . admin_url('admin.php?page=meros-packages') . '">Packages</a>
                            <span class="meros-breadcrumb-separator">/ Settings</span>
                        </nav>';
                        echo '<p>'. $this->getDescription() . '</p>';
                        if ($this->hasRegisteredTables()) {
                            echo '<div style="margin-bottom:2rem;">';
                            echo '<h2>Custom Tables</h2>';
                            echo '<p>It looks like this package has registered custom database tables: ';
                            echo '<a href="' . admin_url('admin.php?page=meros-packages&package=' . $this->getHandle(true) . '&tables=' . $slug . '-tables') . '" title="Manage Custom Database Tables">Manage</a>';
                            echo '</p></div>';
                            echo '<h2>Settings</h2>';

                            if (!$this->isEnabled()) {
                                echo '<p>This package is currently disabled. Please enable it to access its settings.</p>';
                            }
                        }
                    });
                }
            });
        }

        if ($this->hasRegisteredTables() && $settingsPage->getSubPage($slug . '-tables') === null) {
            $this->initTableManagementPage($settingsPage);
        }

        return $settingsPage;
    }

    /**
     * Determines whether the package is enabled.
     *
     * @param boolean $refresh
     *
     * @return boolean
     */
    final protected function isEnabled(bool $refresh = false): bool {
        if (isset($this->enabled) && !$refresh) {
            return $this->enabled;
        }

        $settingName     = $this->getHandle() . '_enabled';
        $packageSettings = Framework::__getPackageSettings($refresh);
        $this->enabled   = (bool) ($packageSettings[$settingName] ?? false);

        return $this->enabled;
    }

    /**
     * Called when the package is enabled. 
     * 
     * For internal use only: Packages should override the whenEnabled() method to define 
     * custom behavior when the package is enabled.
     *
     * @return void
     */
    final public function __whenEnabled(): void {
        $this->getUninstalledRequiredTables()->each(function (Table $table) {
            $table->install();
        });

        $this->whenEnabled();
    }

    /**
     * Called when the package is disabled. 
     * 
     * For internal use only: Packages should override the whenDisabled() method to define 
     * custom behavior when the package is disabled.
     *
     * @return void
     */
    final public function __whenDisabled(): void {
        $this->whenDisabled();
    }

    /**
     * Called when the package is enabled. Packages can override this method to define custom behavior when the package is enabled.
     *
     * @return void
     */
    protected function whenEnabled(): void {
        // This method can be overridden by subclasses to define custom behavior when the package is enabled.
    }


    /**
     * Called when the package is disabled. Packages can override this method to define custom behavior when the package is disabled.
     *
     * @return void
     */
    protected function whenDisabled(): void {
        // This method can be overridden by subclasses to define custom behavior when the package is disabled.
    }
}
