<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model {
    protected $table      = 'posts';
    protected $primaryKey = 'ID';
    public    $timestamps =  false;

    protected $attributes = [
        'post_author'           => 0,
        'post_date'             => '1970-01-01 00:00:00',
        'post_date_gmt'         => '1970-01-01 00:00:00',
        'post_content'          => '',
        'post_title'            => '',
        'post_excerpt'          => '',
        'post_status'           => 'draft',
        'comment_status'        => 'open',
        'ping_status'           => 'open',
        'post_password'         => '',
        'post_name'             => '',
        'to_ping'               => '',
        'pinged'                => '',
        'post_modified'         => '1970-01-01 00:00:00',
        'post_modified_gmt'     => '1970-01-01 00:00:00',
        'post_content_filtered' => '',
        'post_parent'           => 0,
        'guid'                  => '',
        'menu_order'            => 0,
        'post_type'             => 'post',
        'post_mime_type'        => '',
        'comment_count'         => 0,
    ];

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