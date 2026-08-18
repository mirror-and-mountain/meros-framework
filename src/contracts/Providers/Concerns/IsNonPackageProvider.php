<?php

namespace MM\Meros\Contracts\Providers\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use MM\Meros\Contracts\Features\Data\Table;

trait IsNonPackageProvider {
    /**
     * Returns whether the provider has any required tables that are not installed.
     *
     * @return Collection A collection of required tables that are not installed.
     */
    abstract protected function getUninstalledRequiredTables(): Collection;

    /**
     * Returns whether the provider has any registered tables.
     *
     * @return bool True if the provider has registered tables, false otherwise.
     */
    abstract protected function hasRegisteredTables(): bool;

    /**
     * Fires when the theme is activated, triggering any necessary setup actions.
     *
     * @return void
     */
    private function __whenThemeActivated(): void {
        $this->installRequiredTables();
        $this->whenThemeActivated();
    }

    /**
     * Fires when the theme is deactivated, triggering any necessary cleanup actions.
     *
     * @return void
     */
    private function __whenThemeDeactivated(): void {
        $this->whenThemeDeactivated();
    }

    /**
     * Fires when the theme is activated, triggering any necessary setup actions.
     * May be overridden by implementing classes to perform additional actions when the theme is activated.
     *
     * @return void
     */
    protected function whenThemeActivated(): void {}

    /**
     * Fires when the theme is deactivated, triggering any necessary cleanup actions.
     * May be overridden by implementing classes to perform additional actions when the theme is deactivated.
     * @return void
     */
    protected function whenThemeDeactivated(): void {}

    /**
     * Attempts to install any tables required by the theme or framework. Called on theme activation.
     *
     * @return void
     */
    private function installRequiredTables(): void {
        if (!$this->hasRegisteredTables()) {
            return;
        }

        try {
            $uninstalledRequiredTables = $this->getUninstalledRequiredTables();
            if (!$uninstalledRequiredTables->isEmpty()) {
                $uninstalledRequiredTables->each(function (Table $table) {
                    $table->install();
                    $error = $table->getLastError();
                    if ($error !== '') {
                        Log::error("Error installing required table {$table->getName()}: {$error}");
                    }
                });
            }
        } catch (\Exception $e) {
            Log::error("Exception occurred while installing required tables for provider {$this->getHandle()}: " . $e->getMessage());
        }
    }
}