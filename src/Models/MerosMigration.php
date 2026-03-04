<?php

namespace MM\Meros\Models;

use Illuminate\Database\Eloquent\Model;

class MerosMigration extends Model {
    protected $table = 'db_migrations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'path_reference',
        'source',
        'label',
        'priority'
    ];
}