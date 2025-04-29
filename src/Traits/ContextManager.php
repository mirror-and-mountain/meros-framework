<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;

trait ContextManager
{
    protected string $context;
    protected string $contextName;
    protected string $contextUri;

    protected function setContext(): void
    {
        if ( Str::contains( MEROS_BASEPATH, 'themes' ) ) {
            $this->context = 'theme';
        } elseif ( Str::contains( MEROS_BASEPATH, 'plugins' ) ) {
            $this->context = 'plugin';
        }
    }

    protected function setContextName( string $context ): void
    {
        $context = DIRECTORY_SEPARATOR . $context . DIRECTORY_SEPARATOR;
        $pos     = strpos(MEROS_BASEPATH, $context);

        if ($pos === false) {
            $this->contextName = 'unknown';
        }

        // The name of the theme or plugin
        $this->contextName = explode('/', substr(MEROS_BASEPATH, $pos + strlen($context)))[0];
    }

    protected function setContextUri(): void
    {
        $this->contextUri = 
            $this->context === 'theme'
            ? trailingslashit( get_stylesheet_directory_uri() )
            : trailingslashit( plugin_dir_url( __FILE__ ) );
    }
}