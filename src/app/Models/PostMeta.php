<?php 

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class PostMeta extends Model {
    protected $table      = 'postmeta';
    protected $primaryKey = 'meta_id';
    public $timestamps    = false;

    protected $fillable = [
        'post_id',
        'meta_key',
        'meta_value',
    ];

    public function post() {
        return $this->belongsTo(Post::class, 'post_id');
    }
}