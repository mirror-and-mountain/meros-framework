<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Form extends Post {
    /**
     * Scope the query to only include posts of the 'meros-form' post type.
     *
     * @return void
     */
    public function newQuery() {
        return parent::newQuery()->where('post_type', 'meros-form');
    }

    public function form_meta(): HasOne {
        return $this->hasOne(FormMeta::class, 'post_id');
    }

    public function schema(): Attribute {
        return Attribute::make(
            get: fn () => $this->form_meta?->meta_value['schema'] ?? [],
        );
    }

    protected function rows(): Attribute {
        return Attribute::make(
            get: fn (): array => $this->form_meta?->meta_value['schema']['rows'] ?? [],
        );
    }

    protected function actions(): Attribute {
        return Attribute::make(
            get: fn (): array => $this->form_meta?->meta_value['schema']['actions'] ?? [],
        );
    }
}