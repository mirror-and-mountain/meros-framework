<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

use Livewire\Component;
use Livewire\Livewire;

use MM\Meros\Helpers\ClassInfo;

trait ComponentManager
{
    /**
     * Indicates whether the feature has components.
     * Note: by component we mean a Livewire component.
     *
     * @var bool
     */
    protected bool $hasComponents = false;

    /**
     * Indicates whether the feature has views.
     *
     * @var bool
     */
    protected bool $hasViews = false;

    /**
     * The components directory relative to the feature
     * directory.
     *
     * @var string
     */
    protected string $componentsDir = 'components';

    /**
     * The views directory relative to the feature
     * directory.
     *
     * @var string
     */
    protected string $viewsDir = 'views';

    /**
     * Discovered components.
     *
     * @var array
     */
    protected array $components = [];

    /**
     * Discovered views.
     *
     * @var array
     */
    protected array $views = [];

    /**
     * Determines whether component handles should use
     * the feature's fullName. This can be useful if 
     * the feature has a common name and we need to
     * avoid conflicts.
     *
     * @var bool
     */
    protected bool $useFullNameForComponents = false;

    /**
     * Sets absolute path and calls setComponents.
     *
     * @return void
     */
    private function loadComponents(): void
    {
        $componentsPath = $this->path . $this->componentsDir;

        $this->setComponents( $componentsPath );

        $this->hasComponents = $this->components !== [];

        if ( $this->hasComponents ) {
            
            foreach ( $this->components as $handle => $class ) {
                Livewire::component( $handle, $class );
            }
        }
    }

    /**
     * Sets absolute path and calls setViews.
     *
     * @return void
     */
    private function loadViews(): void
    {
        $viewsPath = $this->path . $this->viewsDir;

        $this->setViews( $viewsPath );
        
        $this->hasViews = $this->views !== [];

        if ( $this->hasViews ) {
            $handle = $this->useFullNameForComponents ? $this->fullName : $this->name;
            View::addNamespace( $handle, $viewsPath );
        }
    }

    /**
     * Uses glob to search the given path for valid components.
     * Components will be indentified as a php file that contains
     * a class extending Livewire\\Component.
     *
     * @param  string $path
     * @return void
     */
    private function setComponents( string $path ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }

        $candidates = File::glob( $path . '/*.php' );

        foreach ( $candidates as $component ) {

            $class = ClassInfo::getFromPath( $component );
            
            if ( $class->extends( Component::class ) ) {
                $handle  = $this->useFullNameForComponents ? $this->fullName : $this->name;
                $handle .= '.' . Str::lower( Str::replace( '.php', '', basename( $component )));
                $this->components[ $handle ] = $class->name;
            }
        }
    }

    /**
     * Uses glob to search the given path for valid views.
     * Views will be identified as files with a blade.php extension.
     *
     * @param  string $path
     * @return void
     */
    private function setViews( string $path ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }

        $candidates = File::glob( $path . '/*.blade.php' );

        foreach ( $candidates as $view ) {
            $this->views[] = $view;
        }
    }
}