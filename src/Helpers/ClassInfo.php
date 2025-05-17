<?php 

namespace MM\Meros\Helpers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Provides utilities to inspect classes for
 * validation purposes.
 */
class ClassInfo
{
    /**
     * The fully qualified name of the class.
     *
     * @var string|null
     */
    public ?string $name = null;

    /**
     * The class's namespace
     *
     * @var string|null
     */
    public ?string $namespace = null;

    /**
     * The full path to the file defining the class.
     *
     * @var string|null
     */
    public ?string $path = null;

    /**
     * The URI to the file defining the class.
     *
     * @var string|null
     */
    public ?string $uri = null;

    /**
     * The classes parent if available.
     *
     * @var string|null
     */
    public ?string $parent = null;

    /**
     * Returns instance of this class if the given class 
     * exists. False otherwise. Sets properties for further 
     * inspection by the caller.
     *
     * @param  string    $class
     * @return self|bool
     */
    public static function get( string $class ): self|bool
    {
        if ( class_exists( $class ) ) {
            $instance = new self();
            $instance->setProps( $instance, $class );
            return $instance;
        }

        return false;
    }

    /**
     * Attempts to locate a class based on the given file path
     * and returns an instance of this class if successful.
     * False otherwise. Sets properties for further inspection
     * by the caller.
     *
     * @param  string $path
     * @return self
     */
    public static function getFromPath( string $path ): self|bool
    {
        $instance  = new self();
        $contents  = File::get( $path );
        $namespace = null;

        if ( preg_match('/namespace\s+([\w\\\\]+);/', $contents, $matches) ) {
            $namespace = $matches[1];
        }

        if ( preg_match('/class\s+(\w+)\s+extends/', $contents, $matches) ) {
            $class = $namespace ? "{$namespace}\\{$matches[1]}" : null;

            if ( $class ) {
                require_once $path;
            }

            if ( class_exists( $class ) ) {
                $instance->setProps( $instance, $class );
                return $instance;
            }
        }

        return false;
    }

    /**
     * Uses a Reflection class to set this class's properties.
     *
     * @param  object $instance
     * @param  string $class
     * @return void
     */
    private function setProps( object $instance, string $class ): void
    {
        $reflection           = new \ReflectionClass( $class );
        $instance->name       = $reflection->getName();
        $instance->namespace  = $reflection->getNamespaceName();
        $instance->path       = dirname( $reflection->getFileName() );
        $instance->parent     = $reflection->getParentClass()->getName();

        $themePath = get_theme_file_path();
        $themeUri  = get_template_directory_uri();

        $instance->uri = Str::replaceFirst( $themePath, $themeUri, $instance->path );
    }

    /**
     * Determines whether the given class extends the given
     * base class.
     *
     * @param  string $baseClass
     * @return bool
     */
    public function extends( string $baseClass ): bool
    {
        return $this->name && 
               is_subclass_of($this->name, $baseClass);
    }

    /**
     * Determines whether the given class is descended from the given
     * base class. It will check up to two levels.
     *
     * @param  [type] $baseClass
     * @return bool
     */
    public function isDescendantOf( string $baseClass ): bool
    {
        return $this->name &&
               is_subclass_of( $this->name, $baseClass ) ||
               is_subclass_of( $this->parent, $baseClass );
    }
}
