<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\File;

trait IncludeManager
{

    protected bool   $hasIncludes       = false;
    protected string $includesDir       = 'includes';
    protected array  $includesFileNames = [];
    protected bool   $searchSubDirs     = false;
    protected array  $includes          = [];

    final protected function loadIncludes(): void
    {
        $includesPath = $this->path . $this->includesDir;

        $this->setIncludes( $includesPath, $this->includesFileNames );

        if ( $this->searchSubDirs ) { 

            $subDirs = File::directories( $this->path );

            foreach ( $subDirs as $subDir ) {
                $subIncludesDir = trailingslashit( $subDir ) . $this->includesPath;
                $this->setIncludes( $subIncludesDir, $this->includesFileNames );
            }
        }

        $this->hasIncludes = $this->includes !== [];
    }

    final protected function setIncludes( string $path, array $fileNames ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }

        
        if ( $fileNames !== [] ) {

            foreach ( $fileNames as $file ) {

                $fileToInclude = $path . "/{$file}.php";
                
                if (File::exists( $fileToInclude )) {

                    $this->includes[] = $fileToInclude;
                     
                }
            }
        }

        else {

            $includes = File::glob($path . '/*.php');

            foreach ($includes as $file) {

                $this->includes[] = $file;

            }
        }
    }

    final protected function include(): void
    {
        foreach ($this->includes as $file) {
            include $file;
        }
    }
}