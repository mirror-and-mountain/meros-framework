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
     */
    public function __construct() {
        $directories = Utils::getDirectories( null );

        if ( $directories !== false ) {
            $this->vendorDir   = $directories['vendorDir'] ?? '';
            $this->projectRoot = $directories['projectRoot'] ?? '';
            $this->stubDir     = $directories['stubDir'] ?? '';
            $this->themeConfig = Utils::getThemeConfig( $this->projectRoot, $this->stubDir );

            $this->features   = $this->themeConfig['features'] ?? [];
            $this->extensions = $this->themeConfig['extensions'] ?? [];
            $this->plugins    = $this->themeConfig['plugins'] ?? [];
        }

        if (
            $this->vendorDir === '' ||
            $this->projectRoot === '' ||
            $this->stubDir === '' ||
            $this->themeConfig === []
        ) {
            WP_CLI::error( "Can't configure meros command depenancies. Aborting." );
        }
    }

    /**
     * Creates a new Meros Theme feature.
     *
     * ## OPTIONS
     *
     * [--name=<feature-name>]
     * : The name of the feature to create. This will be converted to StudlyCase.
     *
     *
     * ## EXAMPLES
     *
     * wp meros create-feature MyNewFeature
     * wp meros create-feature my-new-feature
     * wp meros create-feature # will prompt for name
     *
     * @subcommand create-feature
     * @when after_wp_load
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments (e.g., --name=value).
     */
    public function createFeature( $args, $assoc_args ) {
        $featureName = WP_CLI\Utils\get_flag_value( $assoc_args, 'name' );

        // If 'name' parameter is not provided, prompt the user for it.
        if ( empty( $featureName ) ) {
            $featureName = WP_CLI::prompt( 'Please enter the name of the feature to create' );

            // Ensure a name was entered after prompting
            if ( empty( $featureName ) ) {
                WP_CLI::error( 'Feature name cannot be empty. Aborting.' );
                return;
            }
        }

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
            WP_CLI::error( 'Cannot locate create-feature script. Expected at: ' . ( __DIR__ . DIRECTORY_SEPARATOR . 'create-feature.sh' ) . '. Aborting.' );
            return;
        }

        WP_CLI::log( sprintf( 'Attempting to create feature: %s (formatted: %s)', $featureName, $formattedName ) );
        WP_CLI::log( sprintf( 'Using namespace: %s', $namespace ) );
        WP_CLI::log( sprintf( 'Executing script: bash %s %s %s', escapeshellarg( $scriptPath ), escapeshellarg( $formattedName ), escapeshellarg( $namespace ) ) );

        // Construct the command to execute
        $command = 'bash ' . escapeshellarg( $scriptPath ) . ' ' . escapeshellarg( $formattedName ) . ' ' . escapeshellarg( $namespace );

        $output     = [];
        $return_var = 0;

        // Execute the bash script and capture output/return code
        // stderr is redirected to stdout to be captured in $output
        exec( $command . ' 2>&1', $output, $return_var );

        if ( $return_var === 0 ) {
            WP_CLI::success( sprintf( 'Feature "%s" created successfully!', $formattedName ) );
            $this->features[ $namespace ] = $formattedName; // Add newly created feature to the list

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
            WP_CLI::error( sprintf( 'Failed to create feature "%s". Script exited with code %d.', $formattedName, $return_var ) );
        }

        // Output any messages from the bash script
        if ( ! empty( $output ) ) {
            WP_CLI::log( '--- Script Output ---' );
            foreach ( $output as $line ) {
                WP_CLI::log( $line );
            }
            WP_CLI::log( '---------------------' );
        }
    }
}