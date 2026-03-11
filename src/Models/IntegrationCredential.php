<?php

namespace MM\Meros\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationCredential extends Model {
    protected $table = 'integration_credentials';
    protected $primaryKey = 'id';

    protected $fillable = [
        'connection_id',
        'key',
        'secret',
        'type',
        'meta'
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'meta' => 'array'
    ];
}