<?php 

namespace MM\Meros\Helpers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ClassInfo
{
    public ?string $name      = null;
    public ?string $namespace = null;
    public ?string $path      = null;
    public ?string $uri       = null;
    public ?string $parent    = null;

    public static function get( string $class ): self|bool
    {
        if ( class_exists( $class ) ) {
            $instance = new self();
            $instance->setProps( $instance, $class );
            return $instance;
        }

        return false;
    }

    public static function getFromPath( string $path ): self 
    {
        $instance  = new self();
        $contents  = File::get( $path );
        $namespace = null;

        if ( preg_match('/namespace\s+([\w\\\\]+);/', $contents, $matches) ) {
            $namespace = $matches[1];
        }

        if ( preg_match('/class\s+(\w+)\s+extends/', $contents, $matches) ) {
            $class = $namespace ? "{$namespace}\\{$matches[1]}" : null;

            if ( $class && class_exists( $class ) ) {
                $instance->setProps( $instance, $class );
            }
        }

        return $instance;
    }

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

    public function extends( string $baseClass ): bool
    {
        return $this->name && 
               is_subclass_of($this->name, $baseClass);
    }

    public function isDescendantOf( $baseClass ): bool
    {
        return $this->name &&
               is_subclass_of( $this->name, $baseClass ) ||
               is_subclass_of( $this->parent, $baseClass );
    }
}
