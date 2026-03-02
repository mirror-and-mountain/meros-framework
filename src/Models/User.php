<?php 

namespace MM\Meros\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {
    protected $table = 'users';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'user_login',
        'user_email',
        'user_nicename',
        'display_name',
    ];

    public function posts() {
        return $this->hasMany(Post::class, 'post_author');
    }

    public function meta() {
        return $this->hasMany(UserMeta::class, 'user_id');
    }
}