<?php

namespace MM\Meros\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntegrationConnection extends Model {
    protected $table = 'integration_connections';
    protected $primaryKey = 'id';

    protected $fillable = [
        'integration_id',
        'label',
        'account_id',
        'instance_url',
        'settings',
        'is_active',
        'last_used_at'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime'
    ];

    public function credentials(): HasOne {
        return $this->hasOne(IntegrationCredential::class, 'connection_id');
    }

    public function tokens(): HasOne {
        return $this->hasOne(IntegrationToken::class, 'connection_id');
    }
}