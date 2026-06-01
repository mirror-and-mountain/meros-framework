<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldGroupMeta extends PostMeta {
    protected $casts = [
        'meta_value' => 'array',
    ];

    public function newQuery() {
        return parent::newQuery()->where('meta_key', '_meros_field_group_meta');
    }

    public function field_group(): BelongsTo {
        return $this->belongsTo(FieldGroup::class, 'post_id');
    }
}