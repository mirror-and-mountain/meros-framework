<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

use MM\Meros\Contracts\Features\Admin\MenuPage;

use MM\Meros\Contracts\Providers\Concerns\IsNonFrameworkProvider;

use MM\Meros\Support\ClassInfo;

abstract class Package extends Provider {
    use IsNonFrameworkProvider;
    
    // =========================================================================
    // Initialisation
    // =========================================================================

    final protected function afterInit(): void {
        $info = ClassInfo::get(static::class);

        $this->setName(Str::headline($info->shortName));
        $this->setPath($info->path);
        $this->setUri($info->uri);
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

        $menuPageSlug = Str::slug(Str::replace('_', '-', $this->getHandle()));
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
                    $page->callback(function () {
                        echo '<p>'. $this->getDescription() . '</p>';
                    });
                }
            });
        }

        return $settingsPage;
    }
}
