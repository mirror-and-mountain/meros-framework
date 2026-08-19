<?php 

namespace MM\Meros\Registers\Data;

use Illuminate\Support\Collection;

use MM\Meros\Contracts\Register;
use MM\Meros\Contracts\Features\Data\Table;

use MM\Meros\Contracts\Registers\Maker;
use MM\Meros\Contracts\Registers\Concerns\MakesFeatures;

use MM\Meros\Contracts\Concerns\ResolvesPaths;

use MM\Meros\Facades\Data\Tables as TablesFacade;

class Tables extends Register implements Maker {
    use MakesFeatures, ResolvesPaths;

    /**
     * An array of directory paths containing table migration files.
     * Each key represents a feature provider handle and provider's can only
     * register a single path.
     *
     * @var array<string>
     */
    private array $registeredPaths = [];

    protected function configure(): void {
        $this->unique(true);
        $this->contract(Table::class);
        $this->facade(TablesFacade::class);
    }

    /**
     * Instantiates tables from migration files at the provided path, or by using a previously 
     * registered path for the current provider if available.
     *
     * @param string $path
     *
     * @return Collection
     * @throws \InvalidArgumentException if no valid migrations path is provided or registered for the current provider.
     */
    final public function init(?string $path = null): Collection {
        $this->ensureCheckout('init');

        $provider = $this->getProvider();
        $path     = $this->resolvePath($path);

        if (!$this->hasRegisteredTables()) {
            $this->registeredPaths[$provider->getHandle()] = $path;
        }

        $tableDirectoryCandidates = $this->getSubdirectories($path);

        if (empty($tableDirectoryCandidates)) {
            throw new \InvalidArgumentException("No table directories found in the provided path: {$path}");
        }

        $tableDependencies = [];
        foreach ($tableDirectoryCandidates as $tableDirectory) {
            $migrationPathCandidate = $this->getFirstFileInDirectoryWithExtensions($tableDirectory, ['php']);

            if ($migrationPathCandidate !== null) {
                $table = $this->make(['path' => $migrationPathCandidate]);

                if ($table instanceof Table) {
                    $dependencies = $table->getDependencies();
                    if (!empty($dependencies)) {
                        $tableDependencies[$table->getName()] = $dependencies;
                    }
                }

                // Re-checkout on each iteration to ensure the register 
                // remains checked-out until all tables are made.
                $this->checkout($provider);
            }
        }

        $this->setTableDependencies($tableDependencies);
        return $this->all($provider);
    }

    /**
     * Sets dependencies between tables based on the provided array of dependencies.
     *
     * @param array $dependencies
     *
     * @return void
     */
    private function setTableDependencies(array $dependencies): void {
        foreach ($dependencies as $dependent => $dependencyList) {
            $dependentTable = $this->get($dependent);

            if ($dependentTable instanceof Table) {
                foreach ($dependencyList as $dependency) {
                    $dependencyTable = $this->get($dependency);

                    if ($dependencyTable instanceof Table) {
                        $dependencyTable->dependent($dependentTable);
                    }
                }
            }
        }
    }

    /**
     * Registers a directory path containing table migration files for the current provider.
     *
     * @param string|null $path A valid directory path containing table migration files. If null, the provider's default tables path will be used.
     *
     * @return static
     */
    final public function register(?string $path = null): static {
        $this->ensureCheckout('register');
        $path = $this->resolvePath($path);

        $this->registeredPaths[$this->getProvider()->getHandle()] = $path;
        $this->checkin();
        return $this;
    }

    /**
     * Checks if the current provider has registered a tables path.
     *
     * @return boolean
     */
    final public function hasRegisteredTables(): bool {
        $this->ensureCheckout('hasRegisteredTables');
        return isset($this->registeredPaths[$this->getProvider()->getHandle()]);
    }

    /**
     * Resolves the registered tables path for the current provider, checking that the directory looks like a valid migrations directory.
     *
     * @return string
     * @throws \InvalidArgumentException if the provided path is not a valid migrations directory or if no valid path is registered for the provider.
     */
    private function resolvePath(?string $path = null): string {
        $provider    = $this->getProvider();
        $handle      = $provider->getHandle();

        if ($this->registeredPaths[$handle] ?? null) {
            return $this->registeredPaths[$handle];
        }

        $defaultPath = $provider->getPreference('tables_path');

        $path = $this->resolveDirectoryPath($path, $defaultPath);

        if ($path !== null) {
            // Check the path has subdirectories (which should exist for each table)
            $firstSubdirectory = $this->getFirstSubdirectory($path);

            if ($firstSubdirectory !== null) {
                // Check that the first subdirectory contains at least one PHP file (indicating a migration file)
                $hasPHPFiles = $this->directoryHasFileWithExtensions($firstSubdirectory, ['php']);
                if ($hasPHPFiles) {
                    return $path;
                }
            }
        }

        throw new \InvalidArgumentException("No valid tables path registered for provider '{$handle}'.");
    }
}