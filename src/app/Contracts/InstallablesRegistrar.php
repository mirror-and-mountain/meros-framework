<?php 

namespace MM\Meros\App\Contracts;

use Illuminate\Support\Collection;
use MM\Meros\App\Features\Installable;

interface InstallablesRegistrar {
    /**
     * Returns a collection of installable objects.
     * 
     * @param  bool $readyOnly Whether to return only installables that are ready.
     * 
     * @return Collection
     */
    public function getInstallables(bool $readyOnly = false): Collection;

    /**
     * Attempts to install all ready installables registered by the item. On failure, returns a descriptive error message.
     *
     * @return bool|string Returns true on successful installation, or an error message on failure.
     */
    public function install(): bool|string;

    /**
     * Attempts to uninstall all ready installables registered by the item in reverse order. On failure, returns a descriptive error message.
     *
     * @return bool|string Returns true on successful uninstallation, or an error message on failure.
     */
    public function uninstall(): bool|string;

    /**
     * Returns a specific installable object.
     * 
     * @param  string $handle The handle of the installable to return.
     * 
     * @return Installable|null
     */
    public function getInstallable(string $handle): Installable|null;

    /**
     * Return the time the item was installed.
     *
     * @return ?string
     */
    public function getInstalledTime(): ?string;

    /**
     * Return the time the item was last updated.
     *
     * @return ?string
     */
    public function getUpdatedTime(): ?string;
}