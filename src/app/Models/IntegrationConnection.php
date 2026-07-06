<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MM\Meros\Support\Integrations\IntegrationConnectionSecrets;

class IntegrationConnection extends Model {
    protected $table = 'meros_integration_connections';

    protected $fillable = [
        'account_id',
        'label',
        'api_key',
        'access_token',
        'refresh_token',
        'id_token',
        'scopes',
        'metadata',
        'token_expires_at',
        'last_used_at',
        'connected_at',
        'revoked_at',
        'last_refreshed_at',
        'status',
        'status_reason',
        'last_error',
        'last_error_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'id_token' => 'encrypted',
        'scopes' => 'encrypted:array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'connected_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function account(): BelongsTo {
        return $this->belongsTo(IntegrationAccount::class, 'account_id');
    }

    public function secrets(): IntegrationConnectionSecrets {
        return new IntegrationConnectionSecrets($this);
    }
}