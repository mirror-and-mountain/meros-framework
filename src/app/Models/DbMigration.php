<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class DbMigration extends Model {
    protected $table = 'db_migrations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'path_reference',
        'source',
        'label',
        'slug',
        'priority',
        'batch_id'
    ];
}