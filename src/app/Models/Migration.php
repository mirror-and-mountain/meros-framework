<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class Migration extends Model {
    protected $table = 'meros_migrations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'source',
        'type',
        'label',
        'slug',
        'path_reference',
        'batch_id'
    ];
}