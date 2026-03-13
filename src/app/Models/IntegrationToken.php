<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationToken extends Model {
    protected $table = 'integration_tokens';
    protected $primaryKey = 'id';

    protected $fillable = [
        'connection_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'token_type',
        'scopes'
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'scopes' => 'array',
        'expires_at' => 'datetime'
    ];

    public function connection(): BelongsTo {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}