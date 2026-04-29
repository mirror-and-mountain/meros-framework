<?php 

namespace MM\Meros\Services\Registers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

use MM\Meros\Services\Contracts\Table;
use MM\Meros\Services\Contracts\Register;

use MM\Meros\Services\Contracts\Discovery;

class Tables extends Register implements Discovery {
    protected string $identifier = 'handle';
    protected string $definition = Table::class;

    use Concerns\Discovers;

    /**
     * List of supported operations for this register.
     *
     * @var array<string>
     */
    protected array $supports = [
        'attach',
        'get',
        'public',
        'all'
    ];

    /**
     * Discovers tables in the specified path and registers them.
     *
     * @param string|null $path The path to discover tables from. If null, the provider's default tables path will be used.
     *
     * @return void
     */ 
    public function discover(?string $path = null): void {
        $this->ensureCheckedOut();
        $this->discoverTables($path);;
        $this->checkin(); // Check the register back in after discovery
    }

    /**
     * Parses properties for the table's constructor.
     *
     * @param array $props
     *
     * @return array
     */
    protected function parseProperties(array $props): array {
        return []; // No additional properties should be passed to the table constructor.
    }

    /**
     * Discovers and registers tables based on the provided path.
     *
     * @param string|null $path The path to discover tables from.
     *
     * @return void
     */
    protected function discoverTables(?string $path = null): void {
        $provider = $this->provider;

        $path = $this->resolvePath($path, $this->provider->getPreference('tables_path'));

        // Check the path exists and is a directory
        if ($path === null || !File::exists($path) || !File::isDirectory($path)) {
            return;
        }

        // Get subdirectories (each subdirectory should represent a table)
        $directories = File::directories($path);

        foreach ($directories as $directory) {
            // Get the table name from the directory name
            $tableName = Str::snake(basename($directory));
            $tableName = preg_replace('/^(?:\d{4}_\d{2}_\d{2}_\d{6}_|\d+_)/', '', $tableName); // Remove timestamp or numeric prefix if present

            // Discover migration files
            $candidates      = File::files($directory);
            $updateDirectory = $directory . DIRECTORY_SEPARATOR . 'updates';
            
            foreach ($candidates as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $this->attach(new $this->definition(
                    provider:      $this->provider,
                    tableName:     $tableName,
                    migrationPath: $file->getPathname(),
                    updates:       $this->discoverTableUpdates($updateDirectory)
                ));
                
                break; // Only one migration file per table is allowed, so we can stop after the first one is found.
            }

            $this->checkout($provider); // Check the register out after each table is attached to ensure it's available for the next iteration of the loop.
        }
    }

    /**
     * Discovers and registers table updates for a specific table based on the provided updates directory.
     *
     * @param string $updateDirectory
     *
     * @return array
     */
    protected function discoverTableUpdates(string $updateDirectory): array {
        $updates = [];

        if (!File::exists($updateDirectory) || !File::isDirectory($updateDirectory)) {
            return $updates;
        }

        $updateCandidates = File::files($updateDirectory);

        foreach ($updateCandidates as $update) {
            if ($update->getExtension() !== 'php') {
                continue;
            }

            $updates[] = $update->getPathname();
        }

        return $updates;
    }
}