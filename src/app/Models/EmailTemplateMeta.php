<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplateMeta extends PostMeta {
    protected $casts = [
        'meta_value' => 'array',
    ];

    public function newQuery() {
        return parent::newQuery()->where('meta_key', '_meros_email_template_meta');
    }

    public function emailTemplate(): BelongsTo {
        return $this->belongsTo(EmailTemplate::class, 'post_id');
    }
}