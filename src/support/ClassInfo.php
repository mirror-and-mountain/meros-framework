<?php

namespace MM\Meros\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/**
 * Provides utilities to inspect classes for
 * validation purposes.
 */
class ClassInfo {
    /**
     * The fully qualified name of the class.
     * 
     * @var string|null
     */
    public ?string $name = null;

    /**
     * A shortened version of the class name without the namespace.
     * 
     * @var string|null
     */
    public ?string $shortName = null;

    /**
     * The class's namespace
     * 
     * @var string|null
     */
    public ?string $namespace = null;

    /**
     * The full path to the directory containing the class file.
     * 
     * @var string|null
     */
    public ?string $path = null;

    /**
     * The full path to the file defining the class.
     *
     * @var string|null
     */
    public ?string $fullPath = null;

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
     * @param string $class The fully qualified class name.
     * 
     * @return self
     * @throws \InvalidArgumentException If the given class does not exist.
     */
    public static function get(string $class): self {
        if (class_exists($class)) {
            $instance = new self;
            $instance->setProps($instance, $class);

            return $instance;
        }

        throw new \InvalidArgumentException("Class {$class} does not exist.");
    }

    /**
     * Attempts to locate a class based on the given file path
     * and returns an instance of this class if successful.
     * False otherwise. Sets properties for further inspection
     * by the caller.
     *
     * @param string $path The file path to the class.
     * 
     * @return self
     * @throws \InvalidArgumentException If no class is found in the file at the given path.
     */
    public static function getFromPath(string $path): self {
        $instance = new self;
        $contents = File::get($path);
        $namespace = null;

        if (preg_match('/namespace\s+([\w\\\\]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)\s+extends/', $contents, $matches)) {
            $class = $namespace ? "{$namespace}\\{$matches[1]}" : null;

            if ($class) {
                require_once $path;
            }

            if (class_exists($class)) {
                $instance->setProps($instance, $class);

                return $instance;
            }
        }

        throw new \InvalidArgumentException("No class found in file at path {$path}.");
    }

    /**
     * Uses a Reflection class to set this class's properties.
     * 
     * @param object $instance The instance of this class.
     * @param string $class The fully qualified class name.
     * @return void
     */
    private function setProps(object $instance, string $class): void {
        $reflection          = new \ReflectionClass($class);
        $instance->name      = $reflection->getName();
        $instance->shortName = $reflection->getShortName();
        $instance->namespace = $reflection->getNamespaceName();
        $instance->path      = dirname($reflection->getFileName());
        $instance->fullPath  = $reflection->getFileName();
        $instance->parent    = $reflection->getParentClass()->getName();

        $themePath = get_theme_file_path();
        $themeUri  = get_template_directory_uri();

        $instance->uri = Str::replaceFirst($themePath, $themeUri, $instance->path);
    }

    /**
     * Determines whether the given class extends the given
     * base class.
     * 
     * @param string $baseClass
     * @return bool
     */
    public function extends(string $baseClass): bool {
        return $this->name &&
            is_subclass_of($this->name, $baseClass);
    }

    /**
     * Determines whether the given class is descended from the given
     * base class. It will check up to two levels.
     *
     * @param string $baseClass
     * @return bool
     */
    public function isDescendantOf(string $baseClass): bool {
        return $this->name &&
            is_subclass_of($this->name, $baseClass) ||
            is_subclass_of($this->parent, $baseClass);
    }

    /**
     * Determines whether the given class has the given method with the specified visibility.
     * 
     * @param string $method The name of the method to check for.
     * @param string $visibility The visibility to check for ('public', 'protected', 'private').
     * @return bool
     */
    public function hasMethod(string $method, string $visibility = 'public', bool $static = false): bool {
        if (!method_exists($this->name, $method)) {
            return false;
        }

        $reflection = new \ReflectionMethod($this->name, $method);

        if ($static) {
            $reflection = new \ReflectionMethod($this->name, $method);
            if (!$reflection->isStatic()) {
                return false;
            }
        }

        return match($visibility) {
            'public'    => $reflection->isPublic(),
            'protected' => $reflection->isProtected(),
            'private'   => $reflection->isPrivate(),
            default     => false,
        };
    }

    /**
     * Determines whether the given class has the given property with the specified visibility.
     *
     * @param string $property The name of the property to check for.
     * @param string $visibility The visibility to check for ('public', 'protected', 'private').
     * @return bool
     */
    public function hasProperty(string $property, string $visibility = 'public', bool $static = false): bool {
        if (!property_exists($this->name, $property)) {
            return false;
        }

        $reflection = new \ReflectionProperty($this->name, $property);

        if ($static && !$reflection->isStatic()) {
            return false;
        }

        return match($visibility) {
            'public'    => $reflection->isPublic(),
            'protected' => $reflection->isProtected(),
            'private'   => $reflection->isPrivate(),
            default     => false,
        };
    }
}
