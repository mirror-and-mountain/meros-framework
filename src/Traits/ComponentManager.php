<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

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
        $naming         = $this->useFullNameForComponents ? $this->fullName : $this->name;

        $this->setComponents( $componentsPath, $naming );
        $this->discoverComponents();

        $this->hasComponents = $this->components !== [];
    }

    final protected function loadViews(): void
    {
        $viewsPath = $this->path . $this->viewsDir;
        $naming    = $this->useFullNameForComponents ? $this->fullName : $this->name;

        $this->setViews( $viewsPath, $naming );
        $this->discoverViews();
        
        $this->hasViews = $this->views !== [];
    }

    final protected function setComponents( string $path, string $naming ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }

        $candidates = File::glob( $path . '/*.php' );

        foreach ( $candidates as $component ) {

            $class = ClassInfo::getFromPath( $component );
            
            if ( $class->extends( Component::class ) ) {

                $pathInfo = pathinfo( $component );
                $name     = $naming . '_' . Str::slug( $pathInfo['filename'] );

                $this->components[ $name ] = $class->namespace;

            }
        }
    }

    final protected function setViews( string $path, string $naming ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }

        $candidates = File::glob( $path . '/*.blade.php' );

        foreach ( $candidates as $view ) {

            $pathInfo = pathinfo( $view );
            $name     = $naming . '_' . Str::replace( 'blade', '', Str::slug( $pathInfo['filename'] ) );

            $this->views[ $name ] = $path;

        }
    }

    final protected function discoverComponents(): void
    {
        foreach ( $this->components as $name => $component ) {
            Livewire::discover( $name, $component );
        }
    }

    final protected function discoverViews(): void
    {
        foreach ( $this->views as $name => $view ) {
            View::addNamespace( $name, $view );
        }
    }
}