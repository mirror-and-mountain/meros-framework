<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model {
    protected $table      = 'posts';
    protected $primaryKey = 'ID';
    public    $timestamps =  false;

    protected $fillable = [
        'post_title',
        'post_content',
        'post_status',
        'post_excerpt',
        'post_date',
        'post_modified',
        'post_name',
        'post_type',
        'post_author',
    ];

    public function author(): BelongsTo {
        return $this->belongsTo(User::class, 'post_author');
    }

    public function meta(): HasMany {
        return $this->hasMany(PostMeta::class, 'post_id');
    }

    public function scopePublished($query) {
        return $query->where('post_status', 'publish');
    }

    public function scopeOfType($query, $type) {
        return $query->where('post_type', $type);
    }

    public function scopeNotDraft($query) {
        return $query->where('post_title', '!=', 'Auto Draft');
    }
}