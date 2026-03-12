<?php

namespace MM\Meros\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationCredential extends Model {
    protected $table = 'integration_credentials';
    protected $primaryKey = 'id';

    protected $fillable = [
        'integration_id',
        'connection_id',
        'name',
        'type',
        'credentials',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
    ];

    public function integration(): BelongsTo {
        return $this->belongsTo(Integration::class, 'integration_id');
    }

    public function connection(): BelongsTo {
        return $this->belongsTo(IntegrationConnection::class, 'connection_id');
    }
}