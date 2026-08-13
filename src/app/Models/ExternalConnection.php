<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalConnection extends Model {
    protected $table = 'meros_external_connections';

    protected $fillable = [
        'label',
        'integration_id',
        'environment',
        'user_id',
        'api_key',
        'access_token',
        'refresh_token',
        'id_token',
        'scopes',
        'metadata',
        'token_issued_at',
        'token_expires_at',
        'last_used_at',
        'connected_at',
        'revoked_at',
        'last_refreshed_at',
        'is_active',
        'status',
        'status_reason',
        'last_error',
        'last_error_at',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'api_key'           => 'encrypted',
        'access_token'      => 'encrypted',
        'refresh_token'     => 'encrypted',
        'id_token'          => 'encrypted',
        'scopes'            => 'encrypted:array',
        'metadata'          => 'array',
        'token_expires_at'  => 'datetime',
        'last_used_at'      => 'datetime',
        'connected_at'      => 'datetime',
        'revoked_at'        => 'datetime',
        'last_refreshed_at' => 'datetime',
        'last_error_at'     => 'datetime',
    ];

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}