<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormMeta extends PostMeta {
    protected $casts = [
        'meta_value' => 'array',
    ];

    public function newQuery() {
        return parent::newQuery()->where('meta_key', '_meros_form_meta');
    }

    public function form(): BelongsTo {
        return $this->belongsTo(Form::class, 'post_id');
    }
}