<?php 

namespace MM\Meros\Scripts;

use Illuminate\Support\Str;

class MerosCommands {
    /**
     * The theme directory
     *
     * @var string
     */
    private string $projectRoot;

    /**
     * The theme's vendor directory
     *
     * @var string
     */
    private string $vendorDir;

    /**
     * Meros Framework's stub directory
     *
     * @var string
     */
    private string $stubDir;

    /**
     * The theme's configuration.
     *
     * @var array
     */
    private array $themeConfig;

    /**
     * The theme's registered features as provided by
     * the theme config file.
     *
     * @var array
     */
    private array $features;

    /**
     * The theme's registered plugins as provided by
     * the theme config file.
     *
     * @var array
     */
    private array $plugins;

    /**
     * The theme's registered plugins as provided by
     * the theme config file.
     *
     * @var array
     */
    private array $extensions;

    /**
     * Sets up an instance of this class and sets required properties.
     *
     * @return void
     */
    private function init(): bool {
        $directories = Utils::getDirectories( null );

        if ( $directories !== false ) {
            $this->vendorDir   = $directories['vendorDir'] ?? '';
            $this->projectRoot = $directories['projectRoot'] ?? '';
            $this->stubDir     = $directories['stubDir'] ?? '';
            $this->themeConfig = Utils::getThemeConfig( $this->projectRoot, $this->stubDir );

            $this->features   = $this->themeConfig['features'] ?? [];
            $this->extensions = $this->themeConfig['extensions'] ?? [];
            $this->plugins    = $this->themeConfig['plugins'] ?? [];
        } else {
            return false;
        }

        if (
            $this->vendorDir === '' ||
            $this->projectRoot === '' ||
            $this->stubDir === '' ||
            $this->themeConfig === []
        ) {
            return false;
        }

        return true;
    }

    /**
     * Creates a new Meros Theme feature.
     *
     * ## OPTIONS
     *
     * <name>
     * : The name of the feature to create. This will be converted to StudlyCase.
     *
     *
     * ## EXAMPLES
     *
     * wp meros create-feature MyNewFeature
     * wp meros create-feature my-new-feature
     *
     * @subcommand create-feature
     * @when after_wp_load
     *
     * @param array $args Positional arguments.
     */
    public function createFeature( $args ) {
        if ( $this->init() === false ) {
            \WP_CLI::error( 'Cannot extablish required meros properties. Aborting.' );
        }

        list( $featureName ) = $args;

        $formattedName   = Str::studly( $featureName );
        $namespacePrefix = $this->themeConfig['features_namespace'] ?? 'App\\Features'; // Default namespace
        $namespace       = $namespacePrefix . '\\' . $formattedName;

        // Ensure the path is correct relative to where MerosCommands.php resides
        $scriptPath = realpath( __DIR__ . DIRECTORY_SEPARATOR . 'sh' . DIRECTORY_SEPARATOR . 'create-feature.sh' );

        if ( $scriptPath && file_exists( $scriptPath ) ) {
            // Make the script executable
            if ( ! is_executable( $scriptPath ) ) {
                chmod( $scriptPath, 0755 );
            }
        } else {
            \WP_CLI::error( 'Cannot locate create-feature script. Expected at: ' . ( __DIR__ . DIRECTORY_SEPARATOR . 'create-feature.sh' ) . '. Aborting.' );
            return;
        }

        \WP_CLI::log( sprintf( 'Attempting to create feature: %s (formatted: %s)', $featureName, $formattedName ) );
        \WP_CLI::log( sprintf( 'Using namespace: %s', $namespace ) );
        \WP_CLI::log( sprintf( 'Executing script: bash %s %s %s', escapeshellarg( $scriptPath ), escapeshellarg( $formattedName ), escapeshellarg( $namespace ) ) );

        // Construct the command to execute
        $command = 'bash ' . escapeshellarg( $scriptPath ) . ' ' . escapeshellarg( $formattedName ) . ' ' . escapeshellarg( $namespace );
        
        passthru( $command, $return_var );

        if ( $return_var === 0 ) {
            \WP_CLI::success( sprintf( 'Feature "%s" created successfully!', $formattedName ) );
            $this->features[ $namespace . '\\FeatureDefinition' ] = 'FeatureDefinition.php'; // Add newly created feature to the list

            // Regenerate the config file
            Utils::regenerateThemeConfig(
                $this->stubDir,
                $this->projectRoot,
                $this->themeConfig,
                $this->features,
                $this->extensions,
                $this->plugins
            );
        } else {
            \WP_CLI::error( sprintf( 'Failed to create feature "%s". Script exited with code %d.', $formattedName, $return_var ) );
        }
    }

    /**
     * Tests a connection to a remote environment via SSH.
     *
     * ## OPTIONS
     *
     * <environment>
     * : The name of the environment to test a connection to as specified in this devcontainer's remotes.json file.
     *
     * ## EXAMPLES
     *
     * wp meros test-remote stage
     * wp meros test-remote production
     *
     * @subcommand test-remote
     * @when after_wp_load
     *
     * @param array $args Positional arguments.
     */
    public function testRemote( $args ) {
        list( $environment ) = $args;
        $scriptPath = '/usr/local/bin/test-remote.sh';

        if (file_exists($scriptPath)) {
            $command = 'bash ' . escapeshellarg( $scriptPath ) . ' ' . escapeshellarg( $environment );

            passthru( $command, $return_var );

            if ( $return_var === 0 ) {
                \WP_CLI::success( sprintf( 'Test connection to remote environment "%s" ran successfully.', $environment ) );
            } else {
                 \WP_CLI::error( sprintf( 'Failed test connection to remote environment "%s". Script exited with code %d. Output: %s', $environment, $return_var ) );
            }
        }
    }

    /**
     * Synchronises this environment to another environment via SSH.
     *
     * ## OPTIONS
     *
     * <environment>
     * : The name of the environment to sync to as specified in this devcontainer's remotes.json file.
     *
     * [--test=<test>]
     * : Whether to run pre-sync tests.
     * ---
     * default: false
     * options:
     *   - true
     *   - false
     * ---
     *
     * ## EXAMPLES
     *
     * wp meros sync-to stage
     * wp meros sync-to production --test=true
     *
     * @subcommand sync-to
     * @when after_wp_load
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative flags.
     */
    public function syncTo( $args, $assoc_args ) {
        list( $environment ) = $args;
        $runTests   = isset( $assoc_args['test'] ) && $assoc_args['test'] === 'true' ? 'true' : 'false';
        $scriptPath = '/usr/local/bin/sync-to-remote.sh';

        if (file_exists($scriptPath)) {
            $command = 'bash ' . escapeshellarg( $scriptPath ) . ' ' . escapeshellarg( $environment ) . ' ' . escapeshellarg( $runTests );

            // Use passthru() to output the command's output in real-time
            passthru( $command, $return_var );

            if ( $return_var === 0 ) {
                \WP_CLI::success( sprintf( 'Sync to remote environment "%s" completed successfully.', $environment ) );
            } else {
                \WP_CLI::error( sprintf( 'Failed to sync to remote environment "%s". Script exited with code %d.', $environment, $return_var ) );
            }
        }
    }

    /**
     * Synchronises a remote environment to this environment via SSH.
     *
     * ## OPTIONS
     *
     * <environment>
     * : The name of the environment to sync from as specified in this devcontainer's remotes.json file.
     *
     * [--test=<test>]
     * : Whether to run pre-sync tests.
     * ---
     * default: false
     * options:
     *   - true
     *   - false
     * ---
     *
     * ## EXAMPLES
     *
     * wp meros sync-from stage
     * wp meros sync-from production --test=true
     *
     * @subcommand sync-from
     * @when after_wp_load
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative flags.
     */
    public function syncFrom( $args, $assoc_args ) {
        list( $environment ) = $args;
        $runTests   = isset( $assoc_args['test'] ) && $assoc_args['test'] === 'true' ? 'true' : 'false';
        $scriptPath = '/usr/local/bin/sync-from-remote.sh';

        if (file_exists($scriptPath)) {
            $command = 'bash ' . escapeshellarg( $scriptPath ) . ' ' . escapeshellarg( $environment ) . ' ' . escapeshellarg( $runTests );

            passthru( $command, $return_var );

            if ( $return_var === 0 ) {
                \WP_CLI::success( sprintf( 'Sync from remote environment "%s" completed successfully.', $environment ) );
            } else {
                 \WP_CLI::error( sprintf( 'Failed to sync from remote environment "%s". Script exited with code %d. Output: %s', $environment, $return_var ) );
            }
        }
    }
}