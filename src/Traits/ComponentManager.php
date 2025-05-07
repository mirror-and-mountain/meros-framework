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
    protected bool   $hasComponents = false;
    protected bool   $hasViews      = false;
    protected string $componentsDir = 'components';
    protected string $viewsDir      = 'views';
    protected array  $components    = [];
    protected array  $views         = [];

    protected bool   $useFullNameForComponents = false;

    final protected function loadComponents(): void
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

    final protected function loadViews(): void
    {
        $viewsPath = $this->path . $this->viewsDir;

        $this->setViews( $viewsPath );
        
        $this->hasViews = $this->views !== [];

        if ( $this->hasViews ) {
            $handle = $this->useFullNameForComponents ? $this->fullName : $this->name;
            View::addNamespace( $handle, $viewsPath );
        }
    }

    final protected function setComponents( string $path ): void
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

    final protected function setViews( string $path ): void
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