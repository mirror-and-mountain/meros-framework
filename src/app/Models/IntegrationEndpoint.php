<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationEndpoint extends Model {
    protected $table = 'meros_integration_endpoints';

    protected $fillable = [
        'integration_account_id',
        'slug',
        'label',
        'uri',
        'method',
        'format',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function account(): BelongsTo {
        return $this->belongsTo(IntegrationAccount::class, 'integration_account_id');
    }
}