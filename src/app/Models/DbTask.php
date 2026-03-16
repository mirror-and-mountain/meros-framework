<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class DbTask extends Model {
    protected $table = 'db_tasks';
    protected $primaryKey = 'id';

    protected $fillable = [
        'source',
        'type',
        'label',
        'slug',
        'priority',
        'path_reference',
        'batch_id'
    ];
}