<?php 

namespace MM\Meros\Services\Concerns;

use Closure;
use Illuminate\Support\Collection;

use MM\Meros\App\Support\FieldGroup;
use MM\Meros\App\Support\Fields\Field;
use MM\Meros\App\Support\Fields\Maker as FieldMaker;

trait HasFields {
    /**
     * Instantiates a field of the specified type with the given configuration.
     *
     * @param string       $type     The type of field to create (e.g., 'text', 'select').
     * @param Closure|null $callback An optional closure to further configure the field instance after creation.
     * @param array        $config   An optional array of configuration options for the field.
     *
     * @return Field The instantiated Field object.
     */
    protected function field(string $type, ?Closure $callback = null, array $config = []): Field {
        $field = app(FieldMaker::class, ['source' => $this])->make($type, $config);

        if ($callback) {
            $callback($field);
        }
        
        $this->registry()->add('fields', $field);
        return $field;
    }

    /**
     * Retrieves a field by its name or returns a collection of all fields.
     *
     * @param string|null $name The name of the field to retrieve. If null, returns all fields.
     *
     * @return Field|Collection|null The requested field or a collection of all fields. Null if the requested field doesn't exist.
     */
    protected function fields(?string $name = null): Collection {
        if ($name) {
            return $this->registry()->get('fields')->firstWhere('name', $name);
        }

        return $this->registry()->get('fields');
    }

    /**
     * Creates a field group with the specified configuration.
     *
     * @param Closure|string $callbackOrSlug A closure to configure the field group or a string to set the slug.
     *
     * @return FieldGroup The created FieldGroup instance.
     */
    protected function fieldGroup(string $slug, ?Closure $callback = null): FieldGroup {
        $fieldGroup = app(FieldGroup::class, ['source' => $this])->make($slug);

        if ($callback) {
            $callback($fieldGroup);
        }

        $this->registry()->add('fieldGroups', $fieldGroup);
        return $fieldGroup;
    }

    /**
     * Retrieves a field group by its slug or returns a collection of all field groups.
     *
     * @param string|null $slug The slug of the field group to retrieve. If null, returns all field groups.
     *
     * @return FieldGroup|Collection|null The requested field group or a collection of all field groups. Null if the requested field group doesn't exist.
     */
    protected function fieldGroups(?string $slug = null): Collection {
        if ($slug) {
            return $this->registry()->get('fieldGroups')->firstWhere('slug', $slug);
        }

        return $this->registry()->get('fieldGroups');
    }
}