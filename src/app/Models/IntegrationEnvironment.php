<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEnvironment extends Model {
    protected $table = 'meros_integration_environments';

    protected $fillable = [
        'provider',
        'integration_handle',
        'environment',
        'label',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
    ];
}
