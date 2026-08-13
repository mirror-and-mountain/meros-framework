<?php

namespace MM\Meros\App;

use Illuminate\Support\Str;

use MM\Meros\Contracts\Provider;

use MM\Meros\Registers\Admin\SettingsContainers;
use MM\Meros\Contracts\Features\Admin\SettingsContainer;

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
        $containerName = $this->getHandle() . '_settings';

        return $register->get($containerName, $this) ?? 
               $register
                ->checkout($this)
                ->makeFrom($containerName);
    }
}
