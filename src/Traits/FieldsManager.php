<?php 

namespace MM\Meros\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

trait FieldsManager
{
    /**
     * Indicates whether the feature has field types.
     *
     * @var bool
     */
    protected bool $hasFieldTypes = false;

    /**
     * The directory to search for field types in relative to the
     * feature directory.
     *
     * @var string
     */
    protected string $fieldTypesDir = 'fields/build';

    /**
     * Discovered fields.
     *
     * @var array
     */
    protected array $fieldTypes = [];

    /**
     * Discovered field dependancies.
     *
     * @var array
     */
    protected array $fieldTypeDeps = [];
    
    /**
     * Registered field types.
     *
     * @var array
     */
    protected array $registeredFieldTypes = [];

    /**
     * Determines whether script handles should use
     * the feature's fullName. This can be useful if 
     * the feature has a common name and we need to
     * avoid conflicts.
     *
     * @var boolean
     */
    protected bool $useFullNameForFieldTypes = true;

    /**
     * Load field types.
     *
     * @return void
     */
    private function loadFields(): void
    {
        $fieldsPath = $this->path . $this->fieldTypesDir;
        $this->setFields( $fieldsPath );
        $this->registerFieldTypes();

        $this->hasFields = $this->registeredFieldTypes !== [];
    }

    /**
     * Sets the fields found in the given path.
     *
     * @param  string $path
     * @return void
     */
    private function setFields( string $path ): void
    {
        if ( !File::exists( $path ) ) {
            return;
        }

        $fields = File::glob( "{$path}/*/index.js" );

        if ( $fields === [] ) {
            return;
        }

        $i = 0;
        foreach ( $fields as $field ) {
            if ( !File::exists( $field ) ) {
                continue;
            }
            
            $pathInfo = pathinfo( $field );
            $fieldJson = trailingslashit( $pathInfo['dirname'] ) . 'field.json';
            
            if ( !File::exists( $fieldJson ) ) {
                continue;
            }
            
            $dependancyFile = trailingslashit( $pathInfo['dirname'] ) . $pathInfo['filename'] . '.asset.php';
            $dependencies = file_exists( $dependancyFile ) ? include $dependancyFile : [];
            $name = $this->useFullNameForFieldTypes ? $this->fullName : $this->name;
            $handle = $name . '_' . Str::afterLast($pathInfo['dirname'], '/') . '_' . $pathInfo['filename'] . '_' . $i;

            $this->fieldTypeDeps[ $handle ] = $dependencies['dependencies'] ?? [];
            $this->fieldTypes[ $handle ] = Str::replace( $this->path, $this->uri, $field );
            
            $i++;
        }
    }

    private function registerFieldTypes(): void
    {
        add_action('init', function () {
            foreach ($this->fieldTypes as $handle => $src) {
                $registered = wp_register_script( 
                    $handle,
                    $src,
                    $this->fieldTypeDeps[ $handle ] ?? [],
                    filemtime(Str::replace($this->uri, $this->path, $src)),
                    false
                );

                if ( $registered !== false ) {
                    $this->registeredFieldTypes[ $handle ] = $src;
                }
            }
        });
    }

    private function enqueueFieldTypeScripts(): void
    {
        $page = $_GET['page'] ?? '';

        if ( !is_admin() || $page !== 'meros-form-builder' ) {
            return;
        }
        
        add_action('admin_enqueue_scripts', function () {
            foreach ( $this->registeredFieldTypes as $handle => $_ ) {
                wp_enqueue_script( $handle );
            }
            // Reset the hasFieldTypes indicator depending on whether any fields have been discovered.
            $this->hasFieldTypes = $this->registeredFieldTypes !== [];
        });
    }
}