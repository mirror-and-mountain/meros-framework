<?php 

namespace MM\Meros\Helpers;

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

                if ( preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $fileData, $match) && $match[1] ) {

                    $headers[ $field ] = trim($match[1]);

                }
            }

            if ( $headers !== [] ) {
                $pluginsDir      = 'plugins';
                $pluginDir       = baseName($directory);
                $pluginFile      = baseName($candidate);
                $headers['File'] = "{$pluginsDir}/{$pluginDir}/{$pluginFile}";
                return $headers;
            }
        }

        return null;
    }
}