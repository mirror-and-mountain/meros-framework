<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Facades\File;

trait IncludeManager
{
    /**
     * Indicates whether the feature has includes.
     * By includes, we mean an includable php file.
     *
     * @var bool
     */
    protected bool $hasIncludes = false;

    /**
     * The includes directory relative to the
     * feature directory.
     *
     * @var string
     */
    protected string $includesDir = 'includes';

    /**
     * Can be used to search for files with specific names 
     * when searching for includes.
     *
     * @var array
     */
    protected array $includesFileNames = [];

    /**
     * Whether to search in directories nested in the includes
     * directory.
     *
     * @var bool
     */
    protected bool $searchSubDirs = false;

    /**
     * Discovered includes.
     *
     * @var array
     */
    protected array $includes = [];

    /**
     * Sets the absolute path and calls setIncludes.
     *
     * @return void
     */
    private function loadIncludes(): void
    {
        $includesPath = $this->path . $this->includesDir;

        $this->setIncludes( $includesPath, $this->includesFileNames );

        // If searchSubDirs is enabled, search in sub directories for includable files.
        if ( $this->searchSubDirs ) { 

            $subDirs = File::directories( $this->path );

            foreach ( $subDirs as $subDir ) {
                $subIncludesDir = trailingslashit( $subDir ) . $this->includesPath;
                $this->setIncludes( $subIncludesDir, $this->includesFileNames );
            }
        }

        // Resets hasIncludes depending on whether any includes have been discovered.
        $this->hasIncludes = $this->includes !== [];
    }

    /**
     * Searches the given directories for valid include files 
     * and adds them to the incudes property if found.
     *
     * @param  string $path
     * @param  array  $fileNames
     * @return void
     */
    private function setIncludes( string $path, array $fileNames ): void
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

    /**
     * Includes disovered includable files.
     *
     * @return void
     */
    private function include(): void
    {
        foreach ($this->includes as $file) {
            include $file;
        }
    }
}