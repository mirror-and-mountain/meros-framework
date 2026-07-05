<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntegrationAccount extends Model {
    protected $table = 'meros_integration_accounts';

    protected $fillable = [
        'provider',
        'integration_handle',
        'environment',
        'label',
        'category',
        'auth_type',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    public function connections(): HasMany {
        return $this->hasMany(IntegrationConnection::class, 'account_id');
    }

    public function environments(): HasMany {
        return $this->hasMany(IntegrationEnvironment::class, 'integration_handle', 'integration_handle')
            ->where('provider', $this->provider);
    }

    public function defaultEnvironment(): HasOne {
        return $this->hasOne(IntegrationEnvironment::class, 'integration_handle', 'integration_handle')
            ->where('provider', $this->provider)
            ->where('is_default', true);
    }

    public function preferredEnvironment(): ?string {
        $accountEnvironment = $this->getAttribute('environment');

        if (is_string($accountEnvironment) && trim($accountEnvironment) !== '') {
            return $accountEnvironment;
        }

        $defaultEnvironment = $this->defaultEnvironment()->first();

        if ($defaultEnvironment instanceof IntegrationEnvironment) {
            return $defaultEnvironment->environment;
        }

        return 'production';
    }
}