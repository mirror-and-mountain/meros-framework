<?php

namespace MM\Meros\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model {
    protected $table = 'integrations';
    protected $primaryKey = 'id';

    protected $fillable = [
        'source',
        'label',
        'slug',
        'description',
        'api_base_uri',
        'api_version',
        'auth_type',
        'group',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function connections(): HasMany {
        return $this->hasMany(IntegrationConnection::class, 'integration_id');
    }

    public function endpoints(): HasMany {
        return $this->hasMany(IntegrationEndpoint::class, 'integration_id');
    }

    public function credentials(): HasMany {
        return $this->hasMany(IntegrationCredential::class, 'integration_id');
    }
}