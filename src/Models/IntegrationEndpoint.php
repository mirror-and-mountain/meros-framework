<?php

namespace MM\Meros\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEndpoint extends Model {
    protected $table = 'integration_endpoints';
    protected $primaryKey = 'id';

    protected $fillable = [
        'integration_id',
        'source',
        'label',
        'slug',
        'description',
        'uri',
        'method',
        'format',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
}