<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FieldGroup extends Post {
    /**
     * Scope the query to only include posts of the 'meros-field-group' post type.
     *
     * @return void
     */
    public function newQuery() {
        return parent::newQuery()->where('post_type', 'meros-field-group');
    }

    public function fieldGroupMeta(): HasOne {
        return $this->hasOne(FieldGroupMeta::class, 'post_id');
    }

    public function schema(): Attribute {
        return Attribute::make(
            get: fn () => $this->fieldGroupMeta?->meta_value['schema'] ?? [],
        );
    }

    protected function rows(): Attribute {
        return Attribute::make(
            get: fn (): array => $this->fieldGroupMeta?->meta_value['schema']['rows'] ?? [],
        );
    }
}