<?php 

namespace MM\Meros\Helpers;

/**
 * A utility to get and parses plugin data from plugins' main file.
 * Similar approach to the way Wordpress does this.
 */
class PluginInfo
{
    protected static array $validHeaders = [
        'Plugin Name'       => 'Plugin Name',
        'Plugin URI'        => 'Plugin URI',
        'Description'       => 'Description',
        'Version'           => 'Version',
        'Requires at least' => 'Requires at least',
        'Requires PHP'      => 'Requires PHP',
        'Author'            => 'Author',
        'Author URI'        => 'Author URI',
        'License'           => 'License',
        'Licence URI'       => 'License URI',
        'Text Domain'       => 'Text Domain',
        'Domain Path'       => 'Domain Path'
    ];

    /**
     * Attempts to locate a 'main' plugin file in the given
     * plugin directory. If found, this method will parse the
     * file's comments and compare them to the keys provided in
     * the validHeaders property.
     *
     * @param  string     $directory
     * @return array|null
     */
    public static function get( string $directory ): ?array
    {
        $candidateFiles = glob( $directory . '/*.php' );

        foreach ( $candidateFiles as $candidate ) {

            if (!is_file($candidate)) {
                continue;
            }

            $fp       = fopen($candidate, 'r');
            $fileData = fread($fp, 8192);
            fclose($fp);

            $fileData = str_replace("\r", "\n", $fileData);
            $headers  = [];

            foreach ( self::$validHeaders as $field => $regex ) {

                if ( preg_match('/^[ \t\/*#@]*' . 
                     preg_quote($regex, '/') . 
                     ':(.*)$/mi', $fileData, $match) && 
                     $match[1] 
                ) {
                    $headers[ $field ] = trim($match[1]);
                }
            }

            if ( $headers !== [] ) {
                $pluginsDir      = 'plugins';
                $pluginDir       = baseName($directory);
                $pluginFile      = baseName($candidate);
                // Add a key/value for the file's relative path for inclusion later on
                $headers['File'] = "{$pluginsDir}/{$pluginDir}/{$pluginFile}";
                return $headers;
            }
        }

        return null;
    }
}