<?php 

namespace MM\Meros\Scripts;

class Utils 
{
    /**
     * Returns directories relative to this file if possible.
     * False otherwise.
     *
     * @param  string|null   $vendorDir
     * @return array|boolean
     */
    public static function getDirectories( ?string $vendorDir ): array|bool {
        $projectRoot = '';

        if ( !isset($vendorDir) ) {
            $vendorDir = realpath( dirname( __DIR__, 5 ) );
        }

        if ( is_dir($vendorDir) ) {
            $projectRoot = dirname( $vendorDir );
        }
        
        $stubDir = realpath( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'stubs' );

        $loaded = is_dir($vendorDir) && is_dir($projectRoot) && is_dir($stubDir) ? true : false;

        if ( $loaded ) {
            return [
                'vendorDir'   => $vendorDir,
                'projectRoot' => $projectRoot,
                'stubDir'     => $stubDir 
            ];
        } else {
            return false;
        }
    }

     /**
     * Checks that a theme config file exists and creates one if not.
     * Returns contents of file.
     *
     * @return array The theme configuration.
     */
    public static function getThemeConfig( string $projectRoot, string $stubDir ): array {
        $themeConfigPath     = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'theme.php';
        $themeConfigTemplate = $stubDir . DIRECTORY_SEPARATOR . 'theme.template.php';

        // Check if the actual theme config file exists
        if ( ! file_exists( $themeConfigPath ) ) {

            if ( ! file_exists( $themeConfigTemplate ) ) {
                return [];
            }

            // Ensure the config directory exists
            if ( ! is_dir( dirname( $themeConfigPath ) ) ) {
                mkdir( dirname( $themeConfigPath ), 0755, true );
            }

            // Create new config file from template
            $newThemeConfig = copy( $themeConfigTemplate, $themeConfigPath );

            if ( ! $newThemeConfig ) {
                return [];
            }
        }

        // Return the loaded configuration, or an empty array if not found/created
        return file_exists( $themeConfigPath ) ? require $themeConfigPath : [];
    }

    /**
     * Regenerates the theme config file after a feature, extension or
     * plugin is installed.
     *
     * @return void
     */
    public static function regenerateThemeConfig( 
        string $stubDir, 
        string $projectRoot, 
        array  $themeConfig, 
        array  $features,
        array  $extensions,
        array  $plugins
    ): bool {
        $stubPath = $stubDir . DIRECTORY_SEPARATOR . 'ThemeConfig.stub';

        if ( file_exists( $stubPath ) ) {
            $stub     = file_get_contents( $stubPath );
            $rendered = str_replace(
                [
                    '{{theme_class}}',
                    '{{features_namespace}}',
                    '{{extensions_namespace}}',
                    '{{plugins_namespace}}',
                    '{{features}}',
                    '{{extensions}}',
                    '{{plugins}}'
                ],
                [
                    var_export( $themeConfig['theme_class'] ?? 'App\\Theme', true ),
                    var_export( $themeConfig['features_namespace'] ?? 'App\\Features', true ),
                    var_export( $themeConfig['extensions_namespace'] ?? 'App\\Extensions', true ),
                    var_export( $themeConfig['plugins_namespace'] ?? 'App\\Plugins', true ),
                    self::formatArray( $themeConfig, $features, 'features', 2 ),
                    self::formatArray( $themeConfig, $extensions, 'extensions', 2 ),
                    self::formatArray( $themeConfig, $plugins, 'plugins', 2 )
                ],
                $stub
            );

            // Theme config file path relative to project root
            $themeConfigFilePath = $projectRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'theme.php';

            // Ensure the directory exists before writing the file
            if ( ! is_dir( dirname( $themeConfigFilePath ) ) ) {
                mkdir( dirname( $themeConfigFilePath ), 0755, true );
            }

            if ( file_put_contents( $themeConfigFilePath, $rendered ) !== false ) {
                return true;
            }

            return false;
            
        }

        return false;
    }

    /**
     * Formats arrays for the theme config file.
     *
     * @param  array       $array
     * @param  string|null $type
     * @param  int         $indentLevel
     * @return string
     */
    private static function formatArray(
        array $themeConfig, 
        array $array, 
        ?string $type, 
        int $indentLevel = 2 
    ): string {
        $indent = str_repeat( '    ', $indentLevel );
        $lines  = ['['];

        if ( $type !== null ) {
            // Merge with existing config values for the given type
            $array = array_merge( $themeConfig[ $type ] ?? [], $array );
        }

        foreach ( $array as $key => $value ) {
            $formattedKey   = var_export( $key, true );
            $formattedValue = is_array( $value )
                ? self::formatArray( $themeConfig, $value, null, $indentLevel + 1 )
                : var_export( $value, true );

            $lines[] = "{$indent}{$formattedKey} => {$formattedValue},";
        }

        $lines[] = str_repeat( '    ', $indentLevel - 1 ) . ']';

        return implode( "\n", $lines );
    }
}