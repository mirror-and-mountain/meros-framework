<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class Migration extends Model {
    protected $table = 'meros_migrations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'provider',
        'type',
        'label',
        'handle',
        'related_table',
        'path',
        'batch_id'
    ];
}